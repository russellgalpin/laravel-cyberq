<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\CookTemperatureChart;
use App\Filament\Widgets\CurrentCookStats;
use App\Filament\Widgets\FanOutputChart;
use App\Filament\Widgets\FanOutputGauge;
use App\Models\Cook;
use App\Models\Guru;
use App\Models\Probe;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->freezeSecond();
});

it('shows that nothing is cooking', function () {
    $this->get('/')->assertOk();

    Livewire::test(CurrentCookStats::class)->assertSee('No cook in progress');
    Livewire::test(Dashboard::class)->assertActionVisible('startCook');
});

it('shows the latest temperatures against their targets', function () {
    $cook = Cook::factory()->create(['name' => 'Brisket']);
    recordReadings($cook, Probe::PIT, [175 => [2257, 2250]]);
    recordReadings($cook, Probe::FOOD1, [175 => [2040, 2030]]);

    $this->get('/')->assertOk();

    Livewire::test(CurrentCookStats::class)
        ->assertSee('Brisket')
        ->assertSee('225.7°F')
        ->assertSee('Target 225°F')
        ->assertSee('Target 203°F - done');
    Livewire::test(Dashboard::class)->assertActionHidden('startCook');
});

it('estimates how long the food has left', function () {
    $cook = Cook::factory()->create(['started_at' => now()->subHours(4)]);
    recordReadings($cook, Probe::FOOD1, collect(range(0, 6))->mapWithKeys(fn ($step) => [
        200 + $step * 5 => [1500 + $step * 10, 2030],
    ])->all());

    Livewire::test(CurrentCookStats::class)->assertSee('to go');
});

it('warns when the CyberQ has stopped reporting', function () {
    $cook = Cook::factory()->create();
    recordReadings($cook, Probe::PIT, [10 => [2257, 2250]]);

    Livewire::test(CurrentCookStats::class)->assertSee('is the CyberQ on?');
});

it('charts the current cook', function () {
    $cook = Cook::factory()->create();
    recordReadings($cook, Probe::PIT, [0 => [2000, 2250], 1 => [2100, 2250]]);
    recordReadings($cook, Probe::FOOD1, [0 => [500, 2030]]);
    recordReadings($cook, Probe::FAN_OUTPUT, [0 => [80, null]]);

    $temperatures = Livewire::test(CookTemperatureChart::class)->get('options');
    $fan = Livewire::test(FanOutputChart::class)->get('options');

    expect(array_column($temperatures['series'], 'name'))->toBe(['Pit', 'Food 1', 'Pit target', 'Food 1 target'])
        ->and($temperatures['stroke']['dashArray'])->toBe([0, 0, 6, 6])
        ->and($fan['series'][0]['data'][0][1])->toBe(80.0);

    Livewire::test(FanOutputGauge::class)->assertSet('options.series', [80]);
});

it('sets the pit and food targets from the dashboard', function () {
    Http::fake(['cyberq.test/all.xml' => Http::response(cyberQXml('all.xml')), '*' => Http::response()]);
    Guru::factory()->create();

    Livewire::test(Dashboard::class)
        ->mountAction('setTargets')
        ->assertSchemaStateSet(['COOK_SET' => 240.0, 'FOOD1_SET' => 203.0, 'FOOD3_SET' => 190.0])
        ->fillForm(['COOK_SET' => 250, 'FOOD1_SET' => 195])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Targets sent to the CyberQ');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request['COOK_SET'] == 250
        && $request['FOOD1_SET'] == 195
        && $request['FOOD3_SET'] == 190);
});

it('rejects a target the CyberQ cannot reach', function () {
    Http::fake(['cyberq.test/all.xml' => Http::response(cyberQXml('all.xml')), '*' => Http::response()]);
    Guru::factory()->create();

    Livewire::test(Dashboard::class)
        ->callAction('setTargets', data: ['COOK_SET' => 600])
        ->assertHasFormErrors(['COOK_SET' => 'max']);

    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
});

it('explains when the CyberQ is off instead of opening the targets form', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));
    Guru::factory()->create();

    Livewire::test(Dashboard::class)
        ->mountAction('setTargets')
        ->assertNotified('The CyberQ is not responding');
});
