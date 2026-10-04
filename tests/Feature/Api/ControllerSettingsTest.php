<?php

use App\Models\Guru;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
    Guru::factory()->create();
    Http::fake(['cyberq.test/config.xml' => Http::response(cyberQXml('config.xml')), '*' => Http::response()]);
});

it('reads the settings from the CyberQ', function () {
    $this->getJson('/api/v1/controller-settings')
        ->assertOk()
        ->assertJsonPath('data.settings.COOK_SET', 240)
        ->assertJsonPath('data.settings.OPENDETECT', true)
        ->assertJsonPath('data.settings.TIMEOUT_ACTION', 1)
        ->assertJsonPath('data.options.timeout_actions.1', ['value' => 1, 'label' => 'Hold'])
        ->assertJsonPath('data.limits.maximum_fahrenheit', 475)
        ->assertJsonMissingPath('data.settings.WIFI_KEY');
});

it('sends only the changed settings to the CyberQ', function () {
    $this->patchJson('/api/v1/controller-settings', ['COOK_SET' => 250, 'FOOD1_SET' => 203, 'COOK_RAMP' => 1])
        ->assertOk()
        ->assertJsonPath('data.changed', ['COOK_SET', 'COOK_RAMP']);

    Http::assertSent(fn (Request $request) => $request->url() === 'http://cyberq.test/' && $request->body() === 'COOK_SET=250&_COOK_SET=250');
    Http::assertSent(fn (Request $request) => $request->url() === 'http://cyberq.test/control.htm' && $request['COOK_RAMP'] == 1);
});

it('validates settings before sending them', function () {
    $this->patchJson('/api/v1/controller-settings', ['COOK_SET' => 900, 'COOK_TIMER' => 'soon', 'COOK_RAMP' => 9])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['COOK_SET', 'COOK_TIMER', 'COOK_RAMP']);

    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
});

it('reports a CyberQ that is not responding', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    $this->getJson('/api/v1/controller-settings')
        ->assertServiceUnavailable()
        ->assertJsonPath('cyberq_unreachable', true);
});
