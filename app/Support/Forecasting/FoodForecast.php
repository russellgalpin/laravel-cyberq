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
 *
 * Once a joint is wrapped it follows a faster curve. How much faster is only
 * a guess until wrapped cooks have finished, so the forecast also checks how
 * the joint has kept up since it was wrapped, trusting that sooner.
 */
class FoodForecast
{
    public const float MEASURE_SPEED_FROM_FAHRENHEIT = 80;

    /** Once the food has risen this far, its own speed is trusted fully. */
    public const float TRUST_SPEED_AFTER_RISE = 150;

    /** Once a wrapped joint has risen this far, its speed since wrapping is trusted fully. */
    public const float TRUST_WRAPPED_SPEED_AFTER_RISE = 20;

    public const float SLOWEST_SPEED = 1 / 3;

    public const float FASTEST_SPEED = 3;

    public const int LONGEST_FORECAST_HOURS = 48;

    private const int MINIMUM_READINGS_FOR_SPEED = 20;

    public function __construct(
        private readonly CookingCurve $curve,
        private readonly FoodSeries $series,
        private readonly ?CookingCurve $wrappedCurve = null,
        private readonly ?int $wrappedAt = null,
    ) {}

    /** How fast this food is cooking compared with the curve: 2 is twice as fast. */
    public function speed(): float
    {
        $beforeWrapping = $this->isWrapped() ? $this->series->before($this->wrappedAt) : $this->series;
        $speed = self::measuredSpeed($this->curve, $beforeWrapping->from(self::MEASURE_SPEED_FROM_FAHRENHEIT)->points, self::TRUST_SPEED_AFTER_RISE);

        if (! $this->isWrapped()) {
            return $speed;
        }

        return $speed * self::measuredSpeed($this->wrappedCurve->scaledBy($speed), $this->series->since($this->wrappedAt)->points, self::TRUST_WRAPPED_SPEED_AFTER_RISE);
    }

    public function timeToReach(float $targetFahrenheit): ?CarbonInterval
    {
        $latest = $this->series->latest();

        if (! $latest || $latest['food'] >= $targetFahrenheit) {
            return null;
        }

        $curve = $this->isWrapped() ? $this->wrappedCurve : $this->curve;
        $hours = $curve->hoursToReach($latest['food'], $targetFahrenheit, $this->series->recentPit(), $this->speed());

        if ($hours > self::LONGEST_FORECAST_HOURS) {
            return null;
        }

        return CarbonInterval::minutes((int) round($hours * 60))->cascade();
    }

    private function isWrapped(): bool
    {
        return $this->wrappedCurve !== null
            && $this->wrappedAt !== null
            && $this->series->latest() !== null
            && $this->series->latest()['at'] >= $this->wrappedAt;
    }

    /** @param list<array{at: int, food: float, pit: float}> $points */
    private static function measuredSpeed(CookingCurve $curve, array $points, float $trustAfterRise): float
    {
        if (count($points) < self::MINIMUM_READINGS_FOR_SPEED) {
            return 1.0;
        }

        $actualRise = end($points)['food'] - $points[0]['food'];
        $expectedRise = 0.0;

        for ($index = 1; $index < count($points); $index++) {
            $previous = $points[$index - 1];
            $hours = ($points[$index]['at'] - $previous['at']) / 3600;
            $expectedRise += $curve->risePerHour($previous['food'], $previous['pit']) * $hours;
        }

        if ($actualRise <= 0 || $expectedRise <= 0) {
            return 1.0;
        }

        $measured = min(max($actualRise / $expectedRise, self::SLOWEST_SPEED), self::FASTEST_SPEED);
        $trust = min(1, $actualRise / $trustAfterRise);

        return exp($trust * log($measured));
    }
}
