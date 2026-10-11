<?php

namespace App\Support\Forecasting;

use App\Enums\Meat;
use App\Models\Cook;
use App\Models\CookProbe;
use App\Models\Probe;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cooking curves learned from finished cooks: one for everything, and one
 * per meat. A meat's own curve is trusted more the more cooks of it there
 * are, since a couple of cooks can easily be unusual ones.
 */
class CookingHistory
{
    /** A meat with this many cooks counts half its own curve, half everything's. */
    public const int MEAT_CURVE_HALF_TRUST_COOKS = 5;

    /** A run has to get this close to done to show the whole shape of a cook. */
    public const float USEFUL_RUN_PEAK_FAHRENHEIT = 180;

    public const int MINIMUM_COOKS = 2;

    /** @param list<array{meat: ?string, curve: CookingCurve}> $cooks one entry per cook and meat */
    public function __construct(private readonly array $cooks) {}

    public static function load(): self
    {
        $cooks = Cache::remember('cooking-history:'.self::version(), now()->addDay(), fn () => self::fromFinishedCooks()->toArray());

        return self::fromArray($cooks);
    }

    public static function fromFinishedCooks(): self
    {
        $finishedCooks = Cook::query()->whereNotNull('ended_at')->where('ended_at', '<=', now())->with('probes')->get();

        if ($finishedCooks->isEmpty()) {
            return new self([]);
        }

        $readings = DB::table('readings')
            ->join('probes', 'probes.id', '=', 'readings.probe_id')
            ->whereIn('readings.cook_id', $finishedCooks->modelKeys())
            ->whereIn('probes.identifier', Probe::TEMPERATURES)
            ->orderBy('readings.created_at')
            ->get(['readings.cook_id', 'probes.identifier', 'readings.temperature', 'readings.created_at'])
            ->groupBy(['cook_id', 'identifier']);

        $entries = [];

        foreach ($finishedCooks as $cook) {
            $byProbe = $readings->get($cook->id);
            $pit = self::fahrenheitByTime($byProbe?->get(Probe::PIT));

            $runsByMeat = [];

            foreach ([Probe::FOOD1, Probe::FOOD2, Probe::FOOD3] as $identifier) {
                $run = collect(FoodSeries::segments(self::fahrenheitByTime($byProbe?->get($identifier)), $pit))
                    ->first(fn (FoodSeries $series) => $series->peak() >= self::USEFUL_RUN_PEAK_FAHRENHEIT);

                if ($run) {
                    $runsByMeat[$cook->probeMeat($identifier)?->value ?? ''][] = $run;
                }
            }

            foreach ($runsByMeat as $meat => $runs) {
                $curve = CookingCurve::fromSeries($runs);

                if ($curve) {
                    $entries[] = ['meat' => $meat === '' ? null : $meat, 'curve' => $curve];
                }
            }
        }

        return new self($entries);
    }

    /** The curve to forecast with, or null when there are not enough finished cooks yet. */
    public function curveFor(?Meat $meat): ?CookingCurve
    {
        if (count($this->cooks) < self::MINIMUM_COOKS) {
            return null;
        }

        $everything = CookingCurve::average(array_column($this->cooks, 'curve'));
        $sameMeat = array_column(array_filter($this->cooks, fn (array $cook) => $meat && $cook['meat'] === $meat->value), 'curve');

        if (! $everything || $sameMeat === []) {
            return $everything;
        }

        $trust = count($sameMeat) / (count($sameMeat) + self::MEAT_CURVE_HALF_TRUST_COOKS);

        return CookingCurve::average($sameMeat)->blendedWith($everything, $trust);
    }

    public function cookCount(?Meat $meat = null): int
    {
        return count(array_filter($this->cooks, fn (array $cook) => ! $meat || $cook['meat'] === $meat->value));
    }

    /** @return list<array{meat: ?string, bands: array<int, float>}> */
    public function toArray(): array
    {
        return array_map(fn (array $cook) => ['meat' => $cook['meat'], 'bands' => $cook['curve']->toArray()], $this->cooks);
    }

    /** @param list<array{meat: ?string, bands: array<int, float>}> $cooks */
    public static function fromArray(array $cooks): self
    {
        return new self(array_map(fn (array $cook) => ['meat' => $cook['meat'], 'curve' => new CookingCurve($cook['bands'])], $cooks));
    }

    /** Changes whenever a cook finishes, or a finished cook's meats are changed. */
    private static function version(): string
    {
        return md5(json_encode([
            Cook::query()->whereNotNull('ended_at')->count(),
            Cook::query()->max('updated_at'),
            CookProbe::query()->orderBy('id')->pluck('meat', 'id'),
        ]));
    }

    /**
     * @param  iterable<object{temperature: int, created_at: string}>|null  $readings
     * @return array<int, float>
     */
    private static function fahrenheitByTime(?iterable $readings): array
    {
        $fahrenheit = [];

        foreach ($readings ?? [] as $reading) {
            $fahrenheit[strtotime($reading->created_at)] = $reading->temperature / 10;
        }

        return $fahrenheit;
    }
}
