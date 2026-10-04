<?php

namespace App\Support;

use App\Models\Cook;
use App\Models\Probe;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Headline numbers for a cook, worked out from its full-resolution readings.
 */
class CookSummary
{
    public const float PIT_TOLERANCE_FAHRENHEIT = 15.0;

    public function __construct(private readonly CookTimeline $timeline) {}

    public static function for(Cook $cook): self
    {
        return new self(new CookTimeline($cook));
    }

    public function averagePit(): ?float
    {
        return $this->stat(Probe::PIT, fn (Collection $values) => $values->avg());
    }

    public function minimumPit(): ?float
    {
        return $this->stat(Probe::PIT, fn (Collection $values) => $values->min());
    }

    public function maximumPit(): ?float
    {
        return $this->stat(Probe::PIT, fn (Collection $values) => $values->max());
    }

    public function peakTemperature(string $identifier): ?float
    {
        return $this->stat($identifier, fn (Collection $values) => $values->max());
    }

    public function averageFanOutput(): ?float
    {
        $values = $this->timeline->readings(Probe::FAN_OUTPUT)->pluck('temperature');

        return $values->isEmpty() ? null : round($values->avg(), 1);
    }

    /**
     * The share of pit readings that were within PIT_TOLERANCE_FAHRENHEIT of the target, as a percentage.
     */
    public function pitStability(): ?float
    {
        $readingsWithTarget = $this->timeline->readings(Probe::PIT)
            ->filter(fn (object $reading) => $reading->set_point !== null);

        if ($readingsWithTarget->isEmpty()) {
            return null;
        }

        $onTarget = $readingsWithTarget->filter(
            fn (object $reading) => abs($reading->temperature - $reading->set_point) / 10 <= self::PIT_TOLERANCE_FAHRENHEIT
        );

        return round($onTarget->count() / $readingsWithTarget->count() * 100, 1);
    }

    public function reachedTargetAt(string $identifier): ?CarbonInterface
    {
        $reading = $this->timeline->readings($identifier)->first(
            fn (object $reading) => $reading->set_point !== null && $reading->temperature >= $reading->set_point
        );

        return $reading ? Carbon::parse($reading->created_at) : null;
    }

    private function stat(string $identifier, callable $calculate): ?float
    {
        $values = $this->timeline->readings($identifier)->map(fn (object $reading) => $reading->temperature / 10);

        return $values->isEmpty() ? null : round($calculate($values), 1);
    }
}
