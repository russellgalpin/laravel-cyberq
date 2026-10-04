<?php

namespace App\Support;

use App\Services\Guru\DeviceStatus;

/**
 * Maps the pit and food probe targets between the CyberQ (tenths of a degree)
 * and form fields (whole degrees F).
 */
class TargetTemperatures
{
    public const array FIELDS = [
        'COOK_SET',
        'FOOD1_SET',
        'FOOD2_SET',
        'FOOD3_SET',
    ];

    /** @return array<string, float|null> */
    public static function fromDevice(DeviceStatus $status): array
    {
        return collect(self::FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => $status->fahrenheit($field)])
            ->all();
    }
}
