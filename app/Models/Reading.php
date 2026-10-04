<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    use HasFactory;

    protected $fillable = [
        'cook_id',
        'probe_id',
        'temperature',
        'set_point',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'integer',
            'set_point' => 'integer',
        ];
    }

    public function cook(): BelongsTo
    {
        return $this->belongsTo(Cook::class);
    }

    public function probe(): BelongsTo
    {
        return $this->belongsTo(Probe::class);
    }

    protected function temperatureInFahrenheit(): Attribute
    {
        return Attribute::get(fn (): float => $this->temperature / 10);
    }

    protected function setPointInFahrenheit(): Attribute
    {
        return Attribute::get(fn (): ?float => $this->set_point === null ? null : $this->set_point / 10);
    }
}
