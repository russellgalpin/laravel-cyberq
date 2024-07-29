<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cook extends Model
{
    use HasFactory;

    protected $guarded = false;

    public function guru()
    {
        return $this->belongsTo(Guru::class);
    }

    public function readings()
    {
        return $this->hasMany(Reading::class);
    }

    public function inProgress(): Attribute
    {
        return new Attribute(
            get: fn () => !$this->ended_at
        );
    }

    public function pitTemp(): Attribute
    {
        return new Attribute(
            get: fn() => 100
        );
    }
}
