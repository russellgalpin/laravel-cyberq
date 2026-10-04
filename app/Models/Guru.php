<?php

namespace App\Models;

use App\Services\Guru\CyberQ;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guru extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ip',
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    public function probes(): HasMany
    {
        return $this->hasMany(Probe::class);
    }

    public function cooks(): HasMany
    {
        return $this->hasMany(Cook::class);
    }

    /**
     * The CyberQ to control: the one the current cook is on, or the only one there is.
     */
    public static function inUse(): ?self
    {
        return Cook::current()?->guru ?? self::query()->first();
    }

    public function cyberQ(): CyberQ
    {
        return new CyberQ($this);
    }
}
