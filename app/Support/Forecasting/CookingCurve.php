<?php

namespace App\Support\Forecasting;

/**
 * How quickly food rises at each temperature, learned from past cooks.
 *
 * Food heats faster the hotter the pit is compared with it, so each 10°F band
 * holds a rate per square root of that gap: rise per hour = band value x
 * sqrt(pit - food). The square root (rather than the gap itself) is what
 * matched past cooks best; it keeps a low pit from predicting a standstill.
 * The bands carry the shape of a cook: a quick start, the stall around
 * 150-170°F while moisture evaporates, then a slow finish.
 */
readonly class CookingCurve
{
    public const int BAND_FAHRENHEIT = 10;

    public const float PIT_EXPONENT = 0.5;

    public const float MINIMUM_PIT_GAP = 15;

    public const float SLOWEST_RISE_PER_HOUR = 0.3;

    /** Readings above this are past the point anyone pulls meat, so they say nothing useful. */
    private const float IGNORE_ABOVE_FAHRENHEIT = 205;

    private const float SLOWEST_SAMPLE_RISE_PER_HOUR = 0.5;

    /** @param array<int, float> $bands rise per hour per sqrt(°F of pit gap), keyed by band (food °F / 10) */
    public function __construct(public array $bands) {}

    /**
     * One cook's curve: the median rate in each band, from all its runs.
     *
     * @param  list<FoodSeries>  $series
     */
    public static function fromSeries(array $series): ?self
    {
        $samplesByBand = [];

        foreach ($series as $run) {
            foreach ($run->rates() as $sample) {
                $gap = $sample['pit'] - $sample['food'];

                if ($sample['food'] >= self::IGNORE_ABOVE_FAHRENHEIT || $gap < self::MINIMUM_PIT_GAP) {
                    continue;
                }

                $samplesByBand[self::band($sample['food'])][] = max($sample['risePerHour'], self::SLOWEST_SAMPLE_RISE_PER_HOUR) / $gap ** self::PIT_EXPONENT;
            }
        }

        $bands = collect($samplesByBand)
            ->filter(fn (array $samples) => count($samples) >= 2)
            ->map(fn (array $samples) => collect($samples)->median())
            ->all();

        return $bands === [] ? null : new self($bands);
    }

    /**
     * The typical curve across several cooks, each counting equally: the
     * geometric mean of each band, since cooks differ by a factor (a big
     * shoulder is a slow one throughout) rather than by an amount.
     *
     * @param  list<self>  $curves
     */
    public static function average(array $curves): ?self
    {
        $logsByBand = [];

        foreach ($curves as $curve) {
            foreach ($curve->bands as $band => $value) {
                $logsByBand[$band][] = log($value);
            }
        }

        if ($logsByBand === []) {
            return null;
        }

        ksort($logsByBand);

        return new self(array_map(fn (array $logs) => exp(array_sum($logs) / count($logs)), $logsByBand));
    }

    /** This curve with each band pulled towards another curve's; $weight is how much of this one to keep. */
    public function blendedWith(self $other, float $weight): self
    {
        $bands = [];

        foreach ($other->bands as $band => $value) {
            $bands[$band] = exp($weight * log($this->bands[$band] ?? $value) + (1 - $weight) * log($value));
        }

        foreach ($this->bands as $band => $value) {
            $bands[$band] ??= $value;
        }

        ksort($bands);

        return new self($bands);
    }

    public function risePerHour(float $food, float $pit, float $speed = 1.0): float
    {
        return $this->valueAt($food) * $speed * max($pit - $food, self::MINIMUM_PIT_GAP) ** self::PIT_EXPONENT;
    }

    /** Hours to warm from one temperature to another with the pit held steady. */
    public function hoursToReach(float $from, float $to, float $pit, float $speed = 1.0): float
    {
        $hours = 0.0;
        $food = $from;

        while ($food < $to) {
            $step = min(1.0, $to - $food);
            $hours += $step / max($this->risePerHour($food, $pit, $speed), self::SLOWEST_RISE_PER_HOUR);
            $food += $step;
        }

        return $hours;
    }

    /** @return array<int, float> */
    public function toArray(): array
    {
        return $this->bands;
    }

    private function valueAt(float $food): float
    {
        $band = self::band($food);

        if (isset($this->bands[$band])) {
            return $this->bands[$band];
        }

        $nearest = collect(array_keys($this->bands))->sortBy(fn (int $known) => abs($known - $band))->first();

        return $this->bands[$nearest];
    }

    private static function band(float $food): int
    {
        return (int) floor($food / self::BAND_FAHRENHEIT);
    }
}
