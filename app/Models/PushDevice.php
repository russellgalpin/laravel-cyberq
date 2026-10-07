<?php

namespace App\Models;

use App\Enums\ApnsEnvironment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone running the app that wants cook alerts.
 */
class PushDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'environment',
        'pit_alerts',
        'food_alerts',
        'offline_alerts',
    ];

    protected function casts(): array
    {
        return [
            'environment' => ApnsEnvironment::class,
            'pit_alerts' => 'boolean',
            'food_alerts' => 'boolean',
            'offline_alerts' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
