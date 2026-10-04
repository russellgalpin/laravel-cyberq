<?php

namespace App\Models;

use App\Support\LiveActivities\LiveActivityBroadcaster;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
