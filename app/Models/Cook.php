<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cook extends Model
{
    use HasFactory;

    protected $fillable = [
        'guru_id',
        'name',
        'started_at'
    ];

    public function guru()
    {
        return $this->belongsTo(Guru::class);
    }
}
