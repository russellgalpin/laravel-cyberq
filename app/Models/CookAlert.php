<?php

namespace App\Models;

use App\Enums\CookAlertKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where one kind of alert stands for a cook (and probe), so each is sent once
 * rather than on every reading.
 */
class CookAlert extends Model
{
    protected $fillable = [
        'cook_id',
        'kind',
        'probe',
        'target',
        'missed_polls',
        'condition_since',
        'notified_at',
        'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => CookAlertKind::class,
            'target' => 'integer',
            'missed_polls' => 'integer',
            'condition_since' => 'datetime',
            'notified_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public function cook(): BelongsTo
    {
        return $this->belongsTo(Cook::class);
    }

    public function reset(): void
    {
        $this->fill([
            'missed_polls' => 0,
            'condition_since' => null,
            'notified_at' => null,
            'reminded_at' => null,
        ]);
    }
}
