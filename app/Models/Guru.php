<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guru extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'username',
        'ip',
        'username',
        'password'
    ];

    public function probes()
    {
        return $this->hasMany(Probe::class);
    }
}
