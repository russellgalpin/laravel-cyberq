<?php

namespace App\Support;

use App\Models\Cook;
use App\Models\CookNote;
use App\Models\Probe;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The moments that mattered in a cook, in order: when it started, each
 * change of target, each joint wrapped or done, each note, and when it
 * ended. Each one carries every probe's temperature at the time.
 */
class CookEvents
{
    /**
     * Changes to a target this close together are one change: someone
     * stepping through a few values, or the CyberQ ramping the pit down.
     */
    public const int TARGET_SETTLES_AFTER_MINUTES = 15;

    /** A reading this soon after a moment stands in for it when there is none before, as at the start of a cook. */
    public const int READING_AFTER_MINUTES = 10;

    /** A probe pulled out or knocked can read hot for a moment, so a joint is only done after this many readings at its target. */
    public const int DONE_AFTER_READINGS = 2;

    private const array ORDER = ['started', 'pit_target', 'food_target', 'wrapped', 'note', 'done', 'ended'];

    private CookTimeline $timeline;

    /** @var array<string, list<array{0: int, 1: float}>> */
    private array $readingsByTime = [];

    public function __construct(private readonly Cook $cook)
    {
        $this->timeline = new CookTimeline($cook);
    }

    /**
     * @return list<array{
     *     type: string,
     *     at: string,
     *     title: string,
     *     detail: ?string,
     *     probe: ?string,
     *     note: ?string,
     *     note_id: ?int,
     *     temperatures: list<array{identifier: string, label: string, temperature: float}>
     * }>
     */
    public function all(): array
    {
        return collect([
            $this->started(),
            ...$this->targetChanges(Probe::PIT),
            ...$this->foods()->flatMap(fn (string $identifier) => $this->targetChanges($identifier)),
            ...$this->foods()->map(fn (string $identifier) => $this->wrapped($identifier)),
            ...$this->cook->notes->map(fn (CookNote $note) => $this->note($note)),
            ...$this->foods()->map(fn (string $identifier) => $this->done($identifier)),
            $this->ended(),
        ])
            ->filter()
            ->sortBy([
                fn (array $a, array $b) => $a['at'] <=> $b['at'],
                fn (array $a, array $b) => array_search($a['type'], self::ORDER) <=> array_search($b['type'], self::ORDER),
            ])
            ->map(fn (array $event) => [
                'type' => $event['type'],
                'at' => $event['at']->toIso8601String(),
                'title' => $event['title'],
                'detail' => $event['detail'] ?? null,
                'probe' => $event['probe'] ?? null,
                'note' => $event['note'] ?? null,
                'note_id' => $event['note_id'] ?? null,
                'temperatures' => $this->temperaturesAt($event['at']),
            ])
            ->values()
            ->all();
    }

    /** @return Collection<int, string> */
    private function foods(): Collection
    {
        return $this->cook->probesInUse()
            ->pluck('identifier')
            ->reject(fn (string $identifier) => $identifier === Probe::PIT)
            ->values();
    }

    private function started(): ?array
    {
        if (! $this->cook->started_at) {
            return null;
        }

        $pitTarget = $this->timeline->readings(Probe::PIT)->first(fn (object $reading) => $reading->set_point !== null)?->set_point;

        return [
            'type' => 'started',
            'at' => $this->cook->started_at,
            'title' => 'Cook started',
            'detail' => $pitTarget === null ? null : 'Pit target '.self::degrees($pitTarget / 10),
        ];
    }

    private function ended(): ?array
    {
        if ($this->cook->in_progress || ! $this->cook->ended_at) {
            return null;
        }

        return [
            'type' => 'ended',
            'at' => $this->cook->cooked_until ?? $this->cook->ended_at,
            'title' => 'Cook ended',
            'detail' => $this->cook->durationForHumans(short: false),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function targetChanges(string $identifier): array
    {
        $changes = [];
        $current = null;
        $pending = null;

        foreach ($this->timeline->readings($identifier) as $reading) {
            if ($reading->set_point === null) {
                continue;
            }

            $at = Carbon::parse($reading->created_at);
            $target = $reading->set_point / 10;

            if ($current === null) {
                $current = $target;

                continue;
            }

            if (abs($target - $current) < 0.5) {
                continue;
            }

            if ($pending && $pending['last_at']->diffInMinutes($at) <= self::TARGET_SETTLES_AFTER_MINUTES) {
                $pending['to'] = $target;
                $pending['last_at'] = $at;
            } else {
                $changes[] = $pending;
                $pending = ['at' => $at, 'last_at' => $at, 'from' => $current, 'to' => $target];
            }

            $current = $target;
        }

        $changes[] = $pending;

        return collect($changes)
            ->filter()
            ->reject(fn (array $change) => abs($change['to'] - $change['from']) < 0.5)
            ->map(fn (array $change) => [
                'type' => $identifier === Probe::PIT ? 'pit_target' : 'food_target',
                'at' => $change['at'],
                'title' => $identifier === Probe::PIT ?
                    'Pit target changed' :
                    "{$this->name($identifier)} target changed",
                'detail' => self::degrees($change['from']).' to '.self::degrees($change['to']),
                'probe' => $identifier,
            ])
            ->values()
            ->all();
    }

    private function wrapped(string $identifier): ?array
    {
        $wrappedAt = $this->cook->probeWrappedAt($identifier);

        if (! $wrappedAt) {
            return null;
        }

        $temperature = $this->temperatureAt($identifier, $wrappedAt);

        return [
            'type' => 'wrapped',
            'at' => $wrappedAt,
            'title' => "{$this->name($identifier)} wrapped",
            'detail' => $temperature === null ? null : 'At '.self::degrees($temperature),
            'probe' => $identifier,
            'note' => $this->cook->probeWrapNote($identifier),
        ];
    }

    private function note(CookNote $note): array
    {
        return [
            'type' => 'note',
            'at' => $note->noted_at,
            'title' => 'Note',
            'note' => $note->body,
            'note_id' => $note->id,
        ];
    }

    private function done(string $identifier): ?array
    {
        $atTarget = 0;

        foreach ($this->timeline->readings($identifier) as $reading) {
            $reachedTarget = $reading->set_point !== null && $reading->temperature >= $reading->set_point;
            $atTarget = $reachedTarget ? $atTarget + 1 : 0;

            if ($atTarget === 1) {
                $first = $reading;
            }

            if ($atTarget === self::DONE_AFTER_READINGS) {
                return [
                    'type' => 'done',
                    'at' => Carbon::parse($first->created_at),
                    'title' => "{$this->name($identifier)} done",
                    'detail' => 'Reached '.self::degrees($first->set_point / 10),
                    'probe' => $identifier,
                ];
            }
        }

        return null;
    }

    /** @return list<array{identifier: string, label: string, temperature: float}> */
    private function temperaturesAt(CarbonInterface $at): array
    {
        return $this->cook->probesInUse()
            ->map(fn (Probe $probe) => [
                'identifier' => $probe->identifier,
                'label' => $this->name($probe->identifier),
                'temperature' => $this->temperatureAt($probe->identifier, $at),
            ])
            ->filter(fn (array $reading) => $reading['temperature'] !== null)
            ->values()
            ->all();
    }

    private function temperatureAt(string $identifier, CarbonInterface $at): ?float
    {
        $readings = $this->readingsByTime($identifier);
        $timestamp = $at->getTimestamp();
        $low = 0;
        $high = count($readings) - 1;
        $before = null;

        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);

            if ($readings[$middle][0] <= $timestamp) {
                $before = $middle;
                $low = $middle + 1;
            } else {
                $high = $middle - 1;
            }
        }

        if ($before !== null) {
            return $readings[$before][1];
        }

        $first = $readings[0] ?? null;

        if (! $first || $first[0] > $timestamp + self::READING_AFTER_MINUTES * 60) {
            return null;
        }

        return $first[1];
    }

    /** @return list<array{0: int, 1: float}> */
    private function readingsByTime(string $identifier): array
    {
        return $this->readingsByTime[$identifier] ??= $this->timeline->readings($identifier)
            ->map(fn (object $reading) => [Carbon::parse($reading->created_at)->getTimestamp(), $reading->temperature / 10])
            ->values()
            ->all();
    }

    /** Two joints of the same meat need telling apart: "Pulled pork (Food 2)". */
    private function name(string $identifier): string
    {
        if ($identifier === Probe::PIT) {
            return 'Pit';
        }

        $name = $this->cook->probeName($identifier);

        $shared = $this->foods()
            ->reject(fn (string $other) => $other === $identifier)
            ->contains(fn (string $other) => $this->cook->probeName($other) === $name);

        return $shared ? "{$name} (".Probe::LABELS[$identifier].')' : $name;
    }

    private static function degrees(float $fahrenheit): string
    {
        return round($fahrenheit).'°F';
    }
}
