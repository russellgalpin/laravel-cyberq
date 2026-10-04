<?php

namespace App\Support;

use App\Models\Cook;
use App\Models\Probe;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns a cook's readings into chart series. Long cooks can have thousands of
 * readings per probe, so each series is averaged into at most $maxPoints
 * evenly sized time buckets.
 */
class CookTimeline
{
    /**
     * A gap this long in a probe's readings means the cook was really over and
     * the CyberQ was switched off, even if nobody ended the cook.
     */
    public const int COOK_OVER_AFTER_GAP_MINUTES = 120;

    /** @var Collection<string, Collection<int, object>>|null */
    private ?Collection $readingsByIdentifier = null;

    public function __construct(
        private readonly Cook $cook,
        private readonly int $maxPoints = 400,
    ) {}

    /** @return list<array{0: int, 1: float}> */
    public function temperatures(string $identifier): array
    {
        return $this->series($identifier, 'temperature', divisor: 10, aggregate: 'avg');
    }

    /** @return list<array{0: int, 1: float}> */
    public function setPoints(string $identifier): array
    {
        return $this->series($identifier, 'set_point', divisor: 10, aggregate: 'last');
    }

    /** @return list<array{0: int, 1: float}> */
    public function fanOutput(): array
    {
        return $this->series(Probe::FAN_OUTPUT, 'temperature', divisor: 1, aggregate: 'avg');
    }

    /**
     * Temperatures keyed by hours since the cook started, for lining cooks up against each other.
     *
     * @return list<array{0: float, 1: float}>
     */
    public function temperaturesByElapsedHours(string $identifier): array
    {
        $startedAt = $this->cook->started_at->getTimestampMs();
        $activeUntil = $this->activeUntil($identifier)?->getTimestampMs();

        return collect($this->temperatures($identifier))
            ->filter(fn (array $point) => $activeUntil === null || $point[0] <= $activeUntil)
            ->map(fn (array $point) => [round(($point[0] - $startedAt) / 3_600_000, 3), $point[1]])
            ->all();
    }

    /**
     * The last reading before the probe first went quiet for COOK_OVER_AFTER_GAP_MINUTES.
     */
    public function activeUntil(string $identifier): ?CarbonInterface
    {
        $previous = null;

        foreach ($this->readings($identifier) as $reading) {
            $at = Carbon::parse($reading->created_at);

            if ($previous && $previous->diffInMinutes($at) > self::COOK_OVER_AFTER_GAP_MINUTES) {
                return $previous;
            }

            $previous = $at;
        }

        return $previous;
    }

    public function hasReadingsFor(string $identifier): bool
    {
        return $this->readings($identifier)->isNotEmpty();
    }

    /** @return Collection<int, object> */
    public function readings(string $identifier): Collection
    {
        $this->readingsByIdentifier ??= $this->cook->readings()
            ->toBase()
            ->join('probes', 'probes.id', '=', 'readings.probe_id')
            ->orderBy('readings.created_at')
            ->orderBy('readings.id')
            ->get(['probes.identifier', 'readings.temperature', 'readings.set_point', 'readings.created_at'])
            ->groupBy('identifier');

        return $this->readingsByIdentifier->get($identifier, collect());
    }

    /** @return list<array{0: int, 1: float}> */
    private function series(string $identifier, string $column, int $divisor, string $aggregate): array
    {
        $points = $this->readings($identifier)
            ->filter(fn (object $reading) => $reading->{$column} !== null)
            ->map(fn (object $reading) => [
                'at' => Carbon::parse($reading->created_at)->getTimestampMs(),
                'value' => (float) ($reading->{$column} / $divisor),
            ])
            ->values();

        if ($points->count() <= $this->maxPoints) {
            return $points->map(fn (array $point) => [$point['at'], $point['value']])->all();
        }

        $first = $points->first()['at'];
        $bucketWidth = ($points->last()['at'] - $first + 1) / $this->maxPoints;

        return $points
            ->groupBy(fn (array $point) => (int) floor(($point['at'] - $first) / $bucketWidth))
            ->map(fn (Collection $bucket) => [
                (int) round($bucket->avg('at')),
                $aggregate === 'last' ?
                    $bucket->last()['value'] :
                    round($bucket->avg('value'), 1),
            ])
            ->values()
            ->all();
    }

    public function firstReadingAt(string $identifier): ?CarbonInterface
    {
        $first = $this->readings($identifier)->first();

        return $first ? Carbon::parse($first->created_at) : null;
    }
}
