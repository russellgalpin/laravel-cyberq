<?php

namespace App\Support;

use App\Enums\RampProbe;
use App\Enums\TimeoutAction;
use App\Services\Guru\DeviceStatus;
use BackedEnum;
use Illuminate\Validation\Rule;

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

    public const string TIMER_PATTERN = '/^\d{1,2}:[0-5]\d:[0-5]\d$/';

    public const int MINIMUM_FAHRENHEIT = 32;

    public const int MAXIMUM_FAHRENHEIT = 475;

    public const int MINIMUM_BAND_FAHRENHEIT = 5;

    public const int MAXIMUM_BAND_FAHRENHEIT = 100;

    public const int MAXIMUM_CYCLE_SECONDS = 30;

    public const int MAXIMUM_NAME_LENGTH = 31;

    public const string OPEN_LID_FIELD = 'OPENDETECT';

    /**
     * Validation rules for changing settings through the API. Every field is
     * optional so a client can send just what it wants to change.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        $temperature = ['nullable', 'numeric', 'min:'.self::MINIMUM_FAHRENHEIT, 'max:'.self::MAXIMUM_FAHRENHEIT];
        $band = ['nullable', 'numeric', 'min:'.self::MINIMUM_BAND_FAHRENHEIT, 'max:'.self::MAXIMUM_BAND_FAHRENHEIT];
        $name = ['nullable', 'string', 'max:'.self::MAXIMUM_NAME_LENGTH];

        return [
            'COOK_SET' => $temperature,
            'FOOD1_SET' => $temperature,
            'FOOD2_SET' => $temperature,
            'FOOD3_SET' => $temperature,
            'COOKHOLD' => $temperature,
            'ALARMDEV' => $band,
            'PROPBAND' => $band,
            'COOK_NAME' => $name,
            'FOOD1_NAME' => $name,
            'FOOD2_NAME' => $name,
            'FOOD3_NAME' => $name,
            self::TIMER_FIELD => ['nullable', 'string', 'regex:'.self::TIMER_PATTERN],
            'TIMEOUT_ACTION' => ['nullable', Rule::enum(TimeoutAction::class)],
            'COOK_RAMP' => ['nullable', Rule::enum(RampProbe::class)],
            self::OPEN_LID_FIELD => ['nullable', 'boolean'],
            'CYCTIME' => ['nullable', 'integer', 'min:1', 'max:'.self::MAXIMUM_CYCLE_SECONDS],
        ];
    }

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
