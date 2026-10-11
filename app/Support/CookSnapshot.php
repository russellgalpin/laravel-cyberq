<?php

namespace App\Support;

use App\Models\Cook;
use App\Models\Probe;
use App\Models\Reading;
use App\Support\Forecasting\CookingHistory;
use App\Support\Forecasting\FoodForecast;
use App\Support\Forecasting\FoodSeries;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
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
            $this->timeToTarget($identifier, $target, $recent);

        return new ProbeSnapshot(
            identifier: $identifier,
            label: Probe::LABELS[$identifier],
            name: $this->cook->probeName($identifier),
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

    /**
     * From how past cooks went when there are enough of them, otherwise from
     * the straight line the last few readings make.
     *
     * @param  EloquentCollection<int, Reading>  $recent
     */
    private function timeToTarget(string $identifier, float $target, EloquentCollection $recent): ?CarbonInterval
    {
        $curve = CookingHistory::load()->curveFor($this->cook->probeMeat($identifier));
        $series = $curve ? $this->foodSeries($identifier) : null;

        if ($curve && $series && ! $series->isEmpty()) {
            return (new FoodForecast($curve, $series))->timeToReach($target);
        }

        return (new TemperatureForecast($recent->map(fn (Reading $reading) => [
            'at' => $reading->created_at,
            'fahrenheit' => $reading->temperature_in_fahrenheit,
        ])))->timeToReach($target);
    }

    /** The probe's readings since it was last put in (or the cook paused), with the pit's. */
    private function foodSeries(string $identifier): ?FoodSeries
    {
        $readings = $this->cook->readings()
            ->join('probes', 'probes.id', '=', 'readings.probe_id')
            ->whereIn('probes.identifier', [$identifier, Probe::PIT])
            ->toBase()
            ->get(['probes.identifier', 'readings.temperature', 'readings.created_at'])
            ->groupBy('identifier')
            ->map(fn (Collection $readings) => $readings->mapWithKeys(fn (object $reading) => [strtotime($reading->created_at) => $reading->temperature / 10])->all());

        $segments = FoodSeries::segments($readings->get($identifier, []), $readings->get(Probe::PIT, []));

        return $segments === [] ? null : end($segments);
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
