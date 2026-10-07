<?php

namespace App\Models;

use App\Enums\ApnsEnvironment;
use App\Enums\LiveActivityTokenKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveActivityToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'personal_access_token_id',
        'cook_id',
        'kind',
        'environment',
        'token',
    ];

    protected function casts(): array
    {
        return [
            'kind' => LiveActivityTokenKind::class,
            'environment' => ApnsEnvironment::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cook(): BelongsTo
    {
        return $this->belongsTo(Cook::class);
    }
}
