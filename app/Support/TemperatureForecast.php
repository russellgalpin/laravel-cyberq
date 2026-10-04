<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Estimates when a food probe will reach its target by fitting a straight line
 * through its recent readings. Meat stalls and rises unevenly, so this is a
 * rough guide that is only offered while the temperature is clearly climbing.
 */
class TemperatureForecast
{
    private const float MINIMUM_RISE_PER_HOUR = 1.0;

    private const int MINIMUM_READINGS = 5;

    /** @param Collection<int, array{at: CarbonInterface, fahrenheit: float}> $readings */
    public function __construct(private readonly Collection $readings) {}

    public function timeToReach(float $targetFahrenheit): ?CarbonInterval
    {
        if ($this->readings->count() < self::MINIMUM_READINGS) {
            return null;
        }

        $current = $this->readings->last()['fahrenheit'];

        if ($current >= $targetFahrenheit) {
            return null;
        }

        $risePerHour = $this->risePerHour();

        if ($risePerHour < self::MINIMUM_RISE_PER_HOUR) {
            return null;
        }

        $hours = ($targetFahrenheit - $current) / $risePerHour;

        return CarbonInterval::minutes((int) round($hours * 60))->cascade();
    }

    public function risePerHour(): float
    {
        $origin = $this->readings->first()['at'];

        $points = $this->readings->map(fn (array $reading) => [
            'x' => Carbon::parse($origin)->diffInSeconds($reading['at']) / 3600,
            'y' => $reading['fahrenheit'],
        ]);

        $meanX = $points->avg('x');
        $meanY = $points->avg('y');

        $covariance = $points->sum(fn (array $point) => ($point['x'] - $meanX) * ($point['y'] - $meanY));
        $variance = $points->sum(fn (array $point) => ($point['x'] - $meanX) ** 2);

        if ($variance == 0) {
            return 0.0;
        }

        return $covariance / $variance;
    }
}
