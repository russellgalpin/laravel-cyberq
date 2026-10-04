<?php

namespace App\Http\Controllers\Api;

use App\Enums\RampProbe;
use App\Enums\TimeoutAction;
use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Support\ControllerSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControllerSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return $this->settingsResponse(ControllerSettings::fromDevice($this->guru()->cyberQ()->settings()));
    }

    public function update(Request $request): JsonResponse
    {
        $submitted = $request->validate(ControllerSettings::rules());

        $cyberQ = $this->guru()->cyberQ();
        $changes = ControllerSettings::changesForDevice(ControllerSettings::fromDevice($cyberQ->settings()), $submitted);

        if ($changes !== []) {
            $cyberQ->update($changes);
        }

        return $this->settingsResponse(ControllerSettings::fromDevice($cyberQ->settings()), changed: array_keys($changes));
    }

    private function guru(): Guru
    {
        return Guru::inUse() ?? abort(404, 'No CyberQ has been set up yet.');
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<string>  $changed
     */
    private function settingsResponse(array $settings, array $changed = []): JsonResponse
    {
        return response()->json([
            'data' => [
                'settings' => $settings,
                'changed' => $changed,
                'options' => [
                    'timeout_actions' => self::options(TimeoutAction::cases()),
                    'ramp_probes' => self::options(RampProbe::cases()),
                ],
                'limits' => [
                    'minimum_fahrenheit' => ControllerSettings::MINIMUM_FAHRENHEIT,
                    'maximum_fahrenheit' => ControllerSettings::MAXIMUM_FAHRENHEIT,
                    'minimum_band_fahrenheit' => ControllerSettings::MINIMUM_BAND_FAHRENHEIT,
                    'maximum_band_fahrenheit' => ControllerSettings::MAXIMUM_BAND_FAHRENHEIT,
                    'maximum_cycle_seconds' => ControllerSettings::MAXIMUM_CYCLE_SECONDS,
                    'maximum_name_length' => ControllerSettings::MAXIMUM_NAME_LENGTH,
                ],
            ],
        ]);
    }

    /**
     * @param  list<TimeoutAction|RampProbe>  $cases
     * @return list<array{value: int, label: string}>
     */
    private static function options(array $cases): array
    {
        return array_map(fn (TimeoutAction|RampProbe $case) => ['value' => $case->value, 'label' => $case->getLabel()], $cases);
    }
}
