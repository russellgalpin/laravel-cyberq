<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Probe extends Model
{
    use HasFactory;

    public const string PIT = 'COOK_TEMP';

    public const string FOOD1 = 'FOOD1_TEMP';

    public const string FOOD2 = 'FOOD2_TEMP';

    public const string FOOD3 = 'FOOD3_TEMP';

    public const string FAN_OUTPUT = 'OUTPUT_PERCENT';

    public const array TEMPERATURES = [
        self::PIT,
        self::FOOD1,
        self::FOOD2,
        self::FOOD3,
    ];

    protected $fillable = [
        'guru_id',
        'name',
        'identifier',
    ];

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function isTemperature(): bool
    {
        return in_array($this->identifier, self::TEMPERATURES, true);
    }

    public static function setPointKeyFor(string $identifier): ?string
    {
        if (! in_array($identifier, self::TEMPERATURES, true)) {
            return null;
        }

        return Str::replaceLast('_TEMP', '_SET', $identifier);
    }

    public static function statusKeyFor(string $identifier): ?string
    {
        if (! in_array($identifier, self::TEMPERATURES, true)) {
            return null;
        }

        return Str::replaceLast('_TEMP', '_STATUS', $identifier);
    }
}
