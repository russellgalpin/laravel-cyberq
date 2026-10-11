<?php

namespace App\Models;

use App\Enums\Meat;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A probe in use for a cook. Food probes can say what they are in: a meat,
 * a name (like "Big shoulder"), or both.
 */
class CookProbe extends Pivot
{
    protected $table = 'cook_probe';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'meat' => Meat::class,
        ];
    }
}
