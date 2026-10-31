<?php

namespace App\Models;

use App\Enums\JointSize;
use App\Enums\Meat;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A probe in use for a cook. Food probes can say what they are in: a meat,
 * how big the joint is, a name (like "Big shoulder"), or any of them.
 */
class CookProbe extends Pivot
{
    protected $table = 'cook_probe';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'meat' => Meat::class,
            'size' => JointSize::class,
        ];
    }
}
