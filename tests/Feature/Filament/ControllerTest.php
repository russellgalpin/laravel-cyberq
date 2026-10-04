<?php

use App\Enums\RampProbe;
use App\Enums\TimeoutAction;
use App\Filament\Pages\Controller;
use App\Models\Guru;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Guru::factory()->create();
    Http::fake(['cyberq.test/config.xml' => Http::response(cyberQXml('config.xml')), '*' => Http::response()]);
});

it('loads the current settings from the CyberQ', function () {
    $this->get('/controller')->assertOk()->assertSee('CyberQ controller');

    Livewire::test(Controller::class)->assertSchemaStateSet([
        'COOK_SET' => 240.0,
        'FOOD1_SET' => 203.0,
        'FOOD1_NAME' => 'Shoulder',
        'COOK_TIMER' => '01:30:00',
        'TIMEOUT_ACTION' => TimeoutAction::Hold,
        'COOKHOLD' => 200.0,
        'ALARMDEV' => 50.0,
        'COOK_RAMP' => RampProbe::Off,
        'OPENDETECT' => true,
        'CYCTIME' => 6,
        'PROPBAND' => 30.0,
    ]);
});

it('sends only what was changed to the CyberQ', function () {
    Livewire::test(Controller::class)
        ->fillForm([
            'FOOD2_SET' => 165,
            'COOK_RAMP' => 1,
            'OPENDETECT' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Sent 3 changes to the CyberQ');

    Http::assertSent(fn (Request $request) => $request->url() === 'http://cyberq.test/'
        && $request->body() === 'FOOD2_SET=165&_FOOD2_SET=165');
    Http::assertSent(fn (Request $request) => $request->url() === 'http://cyberq.test/control.htm'
        && $request['COOK_RAMP'] == 1
        && $request['OPENDETECT'] === 0
        && ! isset($request['CYCTIME']));
});

it('does not bother the CyberQ when nothing changed', function () {
    Livewire::test(Controller::class)
        ->call('save')
        ->assertNotified('Nothing has changed');

    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
});

it('checks the settings make sense before sending them', function () {
    Livewire::test(Controller::class)
        ->fillForm([
            'COOK_SET' => 900,
            'CYCTIME' => 0,
            'COOK_TIMER' => '90 minutes',
            'FOOD1_NAME' => str_repeat('x', 40),
        ])
        ->call('save')
        ->assertHasFormErrors([
            'COOK_SET' => 'max',
            'CYCTIME' => 'min',
            'COOK_TIMER' => 'regex',
            'FOOD1_NAME' => 'max',
        ]);

    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
});

it('says so when the CyberQ is not responding', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    Livewire::test(Controller::class)
        ->assertSee('The CyberQ is not responding')
        ->assertSet('unreachableReason', fn (string $reason) => str_contains($reason, 'no response'));
});
