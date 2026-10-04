<?php

namespace App\Support;

use App\Services\Guru\DeviceStatus;
use BackedEnum;

/**
 * Maps the CyberQ's settings between the device (temperatures in tenths of a
 * degree F, switches as 0/1) and the controller form (degrees F, booleans).
 */
class ControllerSettings
{
    public const array TEMPERATURE_FIELDS = [
        'COOK_SET',
        'FOOD1_SET',
        'FOOD2_SET',
        'FOOD3_SET',
        'COOKHOLD',
        'ALARMDEV',
        'PROPBAND',
    ];

    public const array INTEGER_FIELDS = [
        'TIMEOUT_ACTION',
        'COOK_RAMP',
        'CYCTIME',
    ];

    public const array TEXT_FIELDS = [
        'COOK_NAME',
        'FOOD1_NAME',
        'FOOD2_NAME',
        'FOOD3_NAME',
    ];

    public const string TIMER_FIELD = 'COOK_TIMER';

    public const string OPEN_LID_FIELD = 'OPENDETECT';

    /** @return array<string, mixed> */
    public static function fromDevice(DeviceStatus $settings): array
    {
        $values = [];

        foreach (self::TEMPERATURE_FIELDS as $field) {
            $values[$field] = $settings->fahrenheit($field);
        }

        foreach (self::INTEGER_FIELDS as $field) {
            $values[$field] = $settings->integer($field);
        }

        foreach (self::TEXT_FIELDS as $field) {
            $values[$field] = $settings->raw($field);
        }

        $values[self::OPEN_LID_FIELD] = $settings->integer(self::OPEN_LID_FIELD) === 1;
        $values[self::TIMER_FIELD] = $settings->raw('TIMER_CURR');

        return $values;
    }

    /**
     * The fields whose value differs from what the device reported, ready to send to it.
     *
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $submitted
     * @return array<string, string|int|float>
     */
    public static function changesForDevice(array $original, array $submitted): array
    {
        return collect($submitted)
            ->map(fn ($value) => self::forDevice($value))
            ->reject(fn ($value) => $value === null || $value === '')
            ->reject(fn ($value, string $field) => self::sameValue($value, self::forDevice($original[$field] ?? null)))
            ->all();
    }

    private static function forDevice(mixed $value): string|int|float|null
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (is_bool($value)) {
            return (int) $value;
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        return $value === null ? null : trim((string) $value);
    }

    private static function sameValue(mixed $submitted, mixed $original): bool
    {
        if (is_numeric($submitted) && is_numeric($original)) {
            return abs($submitted - $original) < 0.05;
        }

        return $submitted === $original;
    }
}
