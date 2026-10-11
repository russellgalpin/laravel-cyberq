<?php

namespace App\Support\Forecasting;

use Carbon\CarbonInterval;

/**
 * When a food probe will reach its target, from the curve past cooks followed
 * and how this joint is keeping up with it so far.
 *
 * Joints differ a lot (a huge shoulder can take four times as long as a small
 * one), so the forecast measures this one's speed against the curve from
 * 80°F onwards, once the outside has warmed past fridge cold. Early on, with
 * little to go on, the speed stays close to typical.
 */
class FoodForecast
{
    public const float MEASURE_SPEED_FROM_FAHRENHEIT = 80;

    /** Once the food has risen this far, its own speed is trusted fully. */
    public const float TRUST_SPEED_AFTER_RISE = 150;

    public const float SLOWEST_SPEED = 1 / 3;

    public const float FASTEST_SPEED = 3;

    public const int LONGEST_FORECAST_HOURS = 48;

    private const int MINIMUM_READINGS_FOR_SPEED = 20;

    public function __construct(
        private readonly CookingCurve $curve,
        private readonly FoodSeries $series,
    ) {}

    /** How fast this food is cooking compared with the curve: 2 is twice as fast. */
    public function speed(): float
    {
        $points = $this->series->from(self::MEASURE_SPEED_FROM_FAHRENHEIT)->points;

        if (count($points) < self::MINIMUM_READINGS_FOR_SPEED) {
            return 1.0;
        }

        $actualRise = end($points)['food'] - $points[0]['food'];
        $expectedRise = 0.0;

        for ($index = 1; $index < count($points); $index++) {
            $previous = $points[$index - 1];
            $hours = ($points[$index]['at'] - $previous['at']) / 3600;
            $expectedRise += $this->curve->risePerHour($previous['food'], $previous['pit']) * $hours;
        }

        if ($actualRise <= 0 || $expectedRise <= 0) {
            return 1.0;
        }

        $measured = min(max($actualRise / $expectedRise, self::SLOWEST_SPEED), self::FASTEST_SPEED);
        $trust = min(1, $actualRise / self::TRUST_SPEED_AFTER_RISE);

        return exp($trust * log($measured));
    }

    public function timeToReach(float $targetFahrenheit): ?CarbonInterval
    {
        $latest = $this->series->latest();

        if (! $latest || $latest['food'] >= $targetFahrenheit) {
            return null;
        }

        $hours = $this->curve->hoursToReach($latest['food'], $targetFahrenheit, $this->series->recentPit(), $this->speed());

        if ($hours > self::LONGEST_FORECAST_HOURS) {
            return null;
        }

        return CarbonInterval::minutes((int) round($hours * 60))->cascade();
    }
}
