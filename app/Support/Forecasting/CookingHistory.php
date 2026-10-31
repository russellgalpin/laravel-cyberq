<?php

namespace App\Support\Forecasting;

use App\Enums\JointSize;
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
 *
 * A big joint cooks slower all the way through, so how much slower large
 * joints are than small ones is learned from cooks of the same meat in both
 * sizes. Each cook's curve is scaled to a medium joint before they are
 * averaged, and the result scaled to the size being cooked.
 */
class CookingHistory
{
    /** A meat with this many cooks counts half its own curve, half everything's. */
    public const int MEAT_CURVE_HALF_TRUST_COOKS = 5;

    /** A run has to get this close to done to show the whole shape of a cook. */
    public const float USEFUL_RUN_PEAK_FAHRENHEIT = 180;

    public const int MINIMUM_COOKS = 2;

    /**
     * With few cooks of each size the difference between them is only partly
     * trusted: three-quarters with one, rising towards all of it with more.
     */
    public const float SIZE_EFFECT_HALF_TRUST_COOKS = 1 / 3;

    /** @param list<array{meat: ?string, size: ?string, curve: CookingCurve}> $cooks one entry per cook, meat and size */
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

            $runsByFood = [];

            foreach ([Probe::FOOD1, Probe::FOOD2, Probe::FOOD3] as $identifier) {
                $run = collect(FoodSeries::segments(self::fahrenheitByTime($byProbe?->get($identifier)), $pit))
                    ->first(fn (FoodSeries $series) => $series->peak() >= self::USEFUL_RUN_PEAK_FAHRENHEIT);

                if ($run) {
                    $runsByFood[($cook->probeMeat($identifier)?->value ?? '').'|'.($cook->probeSize($identifier)?->value ?? '')][] = $run;
                }
            }

            foreach ($runsByFood as $food => $runs) {
                $curve = CookingCurve::fromSeries($runs);
                [$meat, $size] = explode('|', $food);

                if ($curve) {
                    $entries[] = ['meat' => $meat === '' ? null : $meat, 'size' => $size === '' ? null : $size, 'curve' => $curve];
                }
            }
        }

        return new self($entries);
    }

    /** The curve to forecast with, or null when there are not enough finished cooks yet. */
    public function curveFor(?Meat $meat, ?JointSize $size = null): ?CookingCurve
    {
        if (count($this->cooks) < self::MINIMUM_COOKS) {
            return null;
        }

        $factors = $this->sizeFactors();

        $mediumCooks = array_map(fn (array $cook) => [
            ...$cook,
            'curve' => $cook['curve']->scaledBy(1 / ($factors[$cook['size'] ?? ''] ?? 1)),
        ], $this->cooks);

        $everything = CookingCurve::average(array_column($mediumCooks, 'curve'));
        $sameMeat = array_column(array_filter($mediumCooks, fn (array $cook) => $meat && $cook['meat'] === $meat->value), 'curve');

        if (! $everything) {
            return null;
        }

        $curve = $sameMeat === [] ?
            $everything :
            CookingCurve::average($sameMeat)->blendedWith($everything, count($sameMeat) / (count($sameMeat) + self::MEAT_CURVE_HALF_TRUST_COOKS));

        return $curve->scaledBy($factors[$size?->value ?? ''] ?? 1);
    }

    /**
     * How much faster than a medium joint small and large ones cook, from
     * meats cooked in both sizes. Medium sits halfway between them.
     *
     * @return array<string, float>
     */
    public function sizeFactors(): array
    {
        $differences = [];
        $fewestCooks = PHP_INT_MAX;

        foreach (collect($this->cooks)->whereNotNull('meat')->groupBy('meat') as $cooks) {
            $small = $cooks->where('size', JointSize::Small->value)->pluck('curve')->all();
            $large = $cooks->where('size', JointSize::Large->value)->pluck('curve')->all();

            if ($small === [] || $large === []) {
                continue;
            }

            $differences[] = CookingCurve::average($large)->logRatioTo(CookingCurve::average($small));
            $fewestCooks = min($fewestCooks, count($small), count($large));
        }

        if ($differences === []) {
            return [];
        }

        $trust = $fewestCooks / ($fewestCooks + self::SIZE_EFFECT_HALF_TRUST_COOKS);
        $halfDifference = $trust * array_sum($differences) / count($differences) / 2;

        return [
            JointSize::Small->value => exp(-$halfDifference),
            JointSize::Large->value => exp($halfDifference),
        ];
    }

    public function cookCount(?Meat $meat = null): int
    {
        return count(array_filter($this->cooks, fn (array $cook) => ! $meat || $cook['meat'] === $meat->value));
    }

    /** @return list<array{meat: ?string, size: ?string, bands: array<int, float>}> */
    public function toArray(): array
    {
        return array_map(fn (array $cook) => ['meat' => $cook['meat'], 'size' => $cook['size'], 'bands' => $cook['curve']->toArray()], $this->cooks);
    }

    /** @param list<array{meat: ?string, size?: ?string, bands: array<int, float>}> $cooks */
    public static function fromArray(array $cooks): self
    {
        return new self(array_map(fn (array $cook) => ['meat' => $cook['meat'], 'size' => $cook['size'] ?? null, 'curve' => new CookingCurve($cook['bands'])], $cooks));
    }

    /** Changes whenever a cook finishes, or a finished cook's meats or sizes are changed. */
    private static function version(): string
    {
        return md5(json_encode([
            Cook::query()->whereNotNull('ended_at')->count(),
            Cook::query()->max('updated_at'),
            CookProbe::query()->orderBy('id')->get(['id', 'meat', 'size'])->map(fn (CookProbe $probe) => [$probe->id, $probe->meat?->value, $probe->size?->value]),
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
