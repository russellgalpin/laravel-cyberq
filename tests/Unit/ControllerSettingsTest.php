<?php

use App\Enums\RampProbe;
use App\Services\Guru\DeviceStatus;
use App\Support\ControllerSettings;

it('turns device settings into form values', function () {
    $values = ControllerSettings::fromDevice(new DeviceStatus([
        'COOK_SET' => '2400',
        'COOKHOLD' => '2000',
        'ALARMDEV' => '500',
        'CYCTIME' => '6',
        'OPENDETECT' => '1',
        'FOOD1_NAME' => 'Shoulder',
        'TIMER_CURR' => '01:30:00',
    ]));

    expect($values)->toMatchArray([
        'COOK_SET' => 240.0,
        'COOKHOLD' => 200.0,
        'ALARMDEV' => 50.0,
        'CYCTIME' => 6,
        'OPENDETECT' => true,
        'FOOD1_NAME' => 'Shoulder',
        'COOK_TIMER' => '01:30:00',
        'FOOD2_SET' => null,
    ]);
});

it('only sends the settings that were changed', function () {
    $original = ['COOK_SET' => 240.0, 'FOOD1_SET' => 203.0, 'OPENDETECT' => true, 'COOK_NAME' => 'Cook', 'CYCTIME' => 6];

    $changes = ControllerSettings::changesForDevice($original, [
        'COOK_SET' => '250',
        'FOOD1_SET' => '203',
        'OPENDETECT' => false,
        'COOK_NAME' => ' Cook ',
        'CYCTIME' => 6,
        'COOK_RAMP' => RampProbe::Food2,
    ]);

    expect($changes)->toBe(['COOK_SET' => 250, 'OPENDETECT' => 0, 'COOK_RAMP' => 2]);
});

it('never sends blank values', function () {
    expect(ControllerSettings::changesForDevice(['COOK_TIMER' => null, 'FOOD2_SET' => 200.0], ['COOK_TIMER' => '', 'FOOD2_SET' => null]))->toBe([]);
});
