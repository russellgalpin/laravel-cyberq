<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Probe extends Model
{
    use HasFactory;

    protected $fillable = [
        'guru_id',
        'name',
        'identifier'
    ];

    public function guru()
    {
        return $this->belongsTo(Guru::class);
    }
}
