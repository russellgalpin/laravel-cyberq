<?php

namespace App\Models;

use App\Enums\Meat;
use App\Services\Guru\CyberQUnreachable;
use App\Support\LiveActivities\LiveActivityBroadcaster;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Cook extends Model
{
    use HasFactory;

    protected $fillable = [
        'guru_id',
        'name',
        'description',
        'started_at',
        'ended_at',
        'ended_automatically',
    ];

    protected $attributes = [
        'ended_automatically' => false,
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'ended_automatically' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn (Cook $cook) => $cook->readings()->delete());

        static::created(function (Cook $cook) {
            if ($cook->in_progress) {
                app(LiveActivityBroadcaster::class)->start($cook);
            }
        });

        static::updated(function (Cook $cook) {
            if ($cook->wasChanged('ended_at') && ! $cook->in_progress) {
                app(LiveActivityBroadcaster::class)->end($cook);
            }
        });
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    /**
     * The temperature probes chosen for this cook. Cooks from before probes
     * could be chosen have none, and use every probe the CyberQ reports.
     */
    public function probes(): BelongsToMany
    {
        return $this->belongsToMany(Probe::class)
            ->using(CookProbe::class)
            ->withPivot(['meat', 'name']);
    }

    /** @return Collection<int, Probe> */
    public function probesInUse(): Collection
    {
        $probes = $this->probes->isNotEmpty() ?
            $this->probes :
            $this->guru->probes->filter(fn (Probe $probe) => $probe->isTemperature());

        return $probes
            ->sortBy(fn (Probe $probe) => array_search($probe->identifier, Probe::TEMPERATURES, true))
            ->values();
    }

    /**
     * What a probe is called in this cook: its own name, else its meat, else
     * the probe's label ("Food 1").
     */
    public function probeName(string $identifier): string
    {
        $pivot = $this->probes->firstWhere('identifier', $identifier)?->pivot;

        return $pivot?->name ?: $pivot?->meat?->getLabel() ?: Probe::LABELS[$identifier];
    }

    public function probeMeat(string $identifier): ?Meat
    {
        return $this->probes->firstWhere('identifier', $identifier)?->pivot?->meat;
    }

    /**
     * Sends the pit target, and each food probe's done temperature, to the
     * cook's CyberQ. Food probes this cook does not use are left alone.
     *
     * @param  array<string, float|null>  $foodTargets  keyed by probe identifier
     *
     * @throws CyberQUnreachable
     */
    public function sendTargets(?float $pitFahrenheit, array $foodTargets): void
    {
        $foodsInUse = $this->probesInUse()
            ->pluck('identifier')
            ->reject(fn (string $identifier) => $identifier === Probe::PIT);

        $targets = collect([Probe::PIT => $pitFahrenheit])
            ->merge($foodsInUse->mapWithKeys(fn (string $identifier) => [$identifier => $foodTargets[$identifier] ?? null]))
            ->reject(fn (?float $fahrenheit) => $fahrenheit === null)
            ->mapWithKeys(fn (float $fahrenheit, string $identifier) => [Probe::setPointKeyFor($identifier) => $fahrenheit]);

        if ($targets->isEmpty()) {
            return;
        }

        $this->guru->cyberQ()->update($targets->all());
    }

    /**
     * Probes left out of $foods keep what they were already in.
     *
     * @param  list<string>  $identifiers
     * @param  array<string, array{meat?: Meat|string|null, name?: string|null}>  $foods  what each food probe is in, keyed by identifier
     */
    public function useProbes(array $identifiers, array $foods = []): void
    {
        $probeIds = $this->guru->probes()
            ->whereIn('identifier', array_intersect($identifiers, Probe::TEMPERATURES))
            ->pluck('id', 'identifier');

        $current = $this->probes()->get()->keyBy('identifier');

        $this->probes()->sync($probeIds->mapWithKeys(function (int $id, string $identifier) use ($foods, $current) {
            if (! array_key_exists($identifier, $foods)) {
                return [$id => [
                    'meat' => $current->get($identifier)?->pivot->meat?->value,
                    'name' => $current->get($identifier)?->pivot->name,
                ]];
            }

            $name = $foods[$identifier]['name'] ?? null;

            return [$id => [
                'meat' => self::meatValue($foods[$identifier]['meat'] ?? null),
                'name' => filled($name) ? trim($name) : null,
            ]];
        })->all());

        $this->unsetRelation('probes');
    }

    /**
     * A name for a cook nobody named, from what is on its probes.
     *
     * @param  list<Meat>  $meats
     */
    public static function nameFor(array $meats): string
    {
        $labels = collect($meats)->map(fn (Meat $meat) => $meat->getLabel())->unique()->values();

        if ($labels->isEmpty()) {
            return 'Cook on '.now()->format('D j M');
        }

        return ucfirst(strtolower($labels->join(', ', ' and ')));
    }

    private static function meatValue(Meat|string|null $meat): ?string
    {
        return $meat instanceof Meat ?
            $meat->value :
            Meat::tryFrom((string) $meat)?->value;
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query
            ->where('started_at', '<=', now())
            ->where(function (Builder $query) {
                $query
                    ->whereNull('ended_at')
                    ->orWhere('ended_at', '>', now());
            });
    }

    public static function current(): ?self
    {
        return self::query()->active()->latest('started_at')->first();
    }

    protected function inProgress(): Attribute
    {
        return Attribute::get(function (): bool {
            if ($this->started_at?->isFuture()) {
                return false;
            }

            return $this->ended_at === null || $this->ended_at->isFuture();
        });
    }

    public function duration(): ?CarbonInterval
    {
        if (! $this->started_at) {
            return null;
        }

        $end = $this->in_progress ?
            now() :
            $this->ended_at;

        return $this->started_at->diffAsCarbonInterval($end);
    }

    public function durationForHumans(bool $short = true): ?string
    {
        $duration = $this->duration();

        if (! $duration) {
            return null;
        }

        return CarbonInterval::minutes((int) $duration->totalMinutes)
            ->cascade()
            ->forHumans(['short' => $short, 'parts' => 2]);
    }

    public function lastReadingAt(): ?CarbonInterface
    {
        $lastReadingAt = $this->readings_max_created_at ?? $this->readings()->max('created_at');

        return $lastReadingAt ? Carbon::parse($lastReadingAt) : null;
    }

    public function latestReadingFor(string $identifier): ?Reading
    {
        return $this->readings()
            ->whereRelation('probe', 'identifier', $identifier)
            ->latest('id')
            ->first();
    }

    public function end(?CarbonInterface $at = null): void
    {
        $this->update([
            'ended_at' => $at ?? now(),
        ]);
    }

    public function endAutomatically(CarbonInterface $at): void
    {
        $this->update([
            'ended_at' => $at,
            'ended_automatically' => true,
        ]);
    }
}
