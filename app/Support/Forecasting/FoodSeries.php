<?php

namespace App\Support\Forecasting;

/**
 * A food probe's readings paired with the pit temperature at the same moment,
 * oldest first. Times are Unix seconds.
 */
readonly class FoodSeries
{
    /** A gap this long, or a sudden drop, means the probe was moved or the cook paused. */
    public const int BREAK_AFTER_SECONDS = 30 * 60;

    public const float BREAK_ON_DROP_FAHRENHEIT = 25;

    /** @param list<array{at: int, food: float, pit: float}> $points */
    public function __construct(public array $points) {}

    /**
     * Splits readings into unbroken runs. Food readings of zero (no probe
     * plugged in) and readings without a pit reading are skipped.
     *
     * @param  array<int, float>  $food  Fahrenheit keyed by Unix time
     * @param  array<int, float>  $pit  Fahrenheit keyed by Unix time
     * @return list<self>
     */
    public static function segments(array $food, array $pit): array
    {
        ksort($food);

        $segments = [];
        $current = [];

        foreach ($food as $at => $fahrenheit) {
            if ($fahrenheit <= 0 || ! isset($pit[$at])) {
                continue;
            }

            $previous = end($current) ?: null;

            if ($previous && ($at - $previous['at'] > self::BREAK_AFTER_SECONDS || $fahrenheit < $previous['food'] - self::BREAK_ON_DROP_FAHRENHEIT)) {
                $segments[] = new self($current);
                $current = [];
            }

            $current[] = ['at' => $at, 'food' => $fahrenheit, 'pit' => $pit[$at]];
        }

        if ($current !== []) {
            $segments[] = new self($current);
        }

        return $segments;
    }

    public function isEmpty(): bool
    {
        return $this->points === [];
    }

    public function peak(): float
    {
        return max(array_column($this->points, 'food') ?: [0]);
    }

    /** @return array{at: int, food: float, pit: float}|null */
    public function latest(): ?array
    {
        return $this->points === [] ? null : $this->points[array_key_last($this->points)];
    }

    /** The points from when the food first reached a temperature. */
    public function from(float $fahrenheit): self
    {
        $start = null;

        foreach ($this->points as $index => $point) {
            if ($point['food'] >= $fahrenheit) {
                $start = $index;
                break;
            }
        }

        return new self($start === null ? [] : array_slice($this->points, $start));
    }

    /** The average pit temperature over the last few readings. */
    public function recentPit(int $readings = 30): ?float
    {
        $recent = array_slice($this->points, -$readings);

        return $recent === [] ? null : array_sum(array_column($recent, 'pit')) / count($recent);
    }

    /**
     * The food's rate of rise (°F an hour) and the average pit around each
     * sampled reading, from a straight line through the readings either side.
     *
     * @return list<array{food: float, pit: float, risePerHour: float}>
     */
    public function rates(int $everyNthReading = 3, int $windowSeconds = 20 * 60): array
    {
        $samples = [];
        $count = count($this->points);
        $start = 0;
        $end = 0;

        for ($index = 0; $index < $count; $index += $everyNthReading) {
            $at = $this->points[$index]['at'];

            while ($this->points[$start]['at'] < $at - $windowSeconds / 2) {
                $start++;
            }

            while ($end + 1 < $count && $this->points[$end + 1]['at'] <= $at + $windowSeconds / 2) {
                $end++;
            }

            $window = array_slice($this->points, $start, $end - $start + 1);
            $risePerHour = self::slopePerHour($window);

            if (count($window) < 5 || $risePerHour === null) {
                continue;
            }

            $samples[] = [
                'food' => $this->points[$index]['food'],
                'pit' => array_sum(array_column($window, 'pit')) / count($window),
                'risePerHour' => $risePerHour,
            ];
        }

        return $samples;
    }

    /** @param list<array{at: int, food: float, pit: float}> $points */
    private static function slopePerHour(array $points): ?float
    {
        if (count($points) < 2) {
            return null;
        }

        $meanAt = array_sum(array_column($points, 'at')) / count($points);
        $meanFood = array_sum(array_column($points, 'food')) / count($points);
        $covariance = 0;
        $variance = 0;

        foreach ($points as $point) {
            $covariance += ($point['at'] - $meanAt) * ($point['food'] - $meanFood);
            $variance += ($point['at'] - $meanAt) ** 2;
        }

        return $variance == 0 ? null : $covariance / $variance * 3600;
    }
}
