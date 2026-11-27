<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Something worth remembering about a cook, at the moment it happened. */
class CookNote extends Model
{
    protected $fillable = [
        'body',
        'noted_at',
    ];

    protected function casts(): array
    {
        return [
            'noted_at' => 'datetime',
        ];
    }

    public function cook(): BelongsTo
    {
        return $this->belongsTo(Cook::class);
    }
}
