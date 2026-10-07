<?php

namespace App\Support;

use App\Models\Cook;
use App\Models\Probe;
use App\Models\Reading;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The latest state of a cook: each probe's temperature against its target,
 * an estimate of how long the food has left, and whether readings are still
 * arriving. Shared by the dashboard and the API.
 */
class CookSnapshot
{
    public const int STALE_AFTER_MINUTES = 5;

    public const int FORECAST_WINDOW_MINUTES = 45;

    /** @var array<string, EloquentCollection<int, Reading>> */
    private array $recentReadings = [];

    private ?CarbonInterface $lastReadingAt = null;

    private bool $lastReadingLoaded = false;

    public function __construct(public readonly Cook $cook) {}

    public function lastReadingAt(): ?CarbonInterface
    {
        if (! $this->lastReadingLoaded) {
            $this->lastReadingAt = $this->cook->lastReadingAt();
            $this->lastReadingLoaded = true;
        }

        return $this->lastReadingAt;
    }

    public function isStale(): bool
    {
        $lastReadingAt = $this->lastReadingAt();

        return $lastReadingAt !== null && $lastReadingAt->lessThan(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    /** @return Collection<int, ProbeSnapshot> */
    public function probes(): Collection
    {
        return $this->cook->probesInUse()
            ->map(fn (Probe $probe) => $this->probe($probe->identifier))
            ->filter()
            ->values();
    }

    public function probe(string $identifier): ?ProbeSnapshot
    {
        $latest = $this->cook->latestReadingFor($identifier);

        if (! $latest) {
            return null;
        }

        $target = $latest->set_point_in_fahrenheit;
        $recent = $this->recentReadings($identifier);

        $timeToTarget = $identifier === Probe::PIT || $target === null ?
            null :
            (new TemperatureForecast($recent->map(fn (Reading $reading) => [
                'at' => $reading->created_at,
                'fahrenheit' => $reading->temperature_in_fahrenheit,
            ])))->timeToReach($target);

        return new ProbeSnapshot(
            identifier: $identifier,
            label: Probe::LABELS[$identifier],
            temperature: $latest->temperature_in_fahrenheit,
            target: $target,
            timeToTarget: $timeToTarget,
            recentTemperatures: $recent->pluck('temperature_in_fahrenheit')->all(),
        );
    }

    public function fanOutput(): ?int
    {
        return $this->cook->latestReadingFor(Probe::FAN_OUTPUT)?->temperature;
    }

    /** @return EloquentCollection<int, Reading> */
    private function recentReadings(string $identifier): EloquentCollection
    {
        return $this->recentReadings[$identifier] ??= $this->cook->readings()
            ->whereRelation('probe', 'identifier', $identifier)
            ->where('created_at', '>=', now()->subMinutes(self::FORECAST_WINDOW_MINUTES))
            ->orderBy('created_at')
            ->get();
    }
}
