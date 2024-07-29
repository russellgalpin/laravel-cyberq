<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reading extends Model
{
    use HasFactory;

    protected $fillable = [
        'cook_id',
        'probe_id',
        'temperature',
        'set_point'
    ];

    public function probe()
    {
        return $this->belongsTo(Probe::class);
    }

    public function temperatureInFahrenheit(): Attribute
    {
        return new Attribute(
            get: fn() => $this->temperature / 10
        );
    }

    public function setPointInFahrenheit(): Attribute
    {
        return new Attribute(
            get: fn() => $this->set_point / 10
        );
    }
}
