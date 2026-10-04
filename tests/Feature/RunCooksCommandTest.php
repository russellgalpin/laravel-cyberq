<?php

use App\Models\Cook;
use App\Models\Probe;
use App\Models\Reading;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake(['cyberq.test/all.xml' => Http::response(cyberQXml('all.xml'))]);
});

it('records a reading for every connected probe of a cook in progress', function () {
    $cook = Cook::factory()->create();

    $this->artisan('cooks:run')
        ->expectsOutputToContain('Recorded 4 readings')
        ->assertSuccessful();

    $readings = $cook->readings()->with('probe')->get()->keyBy('probe.identifier');

    expect($readings)->toHaveCount(4)
        ->and($readings->keys()->all())->toEqualCanonicalizing([Probe::PIT, Probe::FOOD1, Probe::FAN_OUTPUT, 'COOK_RAMP'])
        ->and($readings[Probe::PIT]->temperature)->toBe(2657)
        ->and($readings[Probe::PIT]->set_point)->toBe(2400)
        ->and($readings[Probe::FOOD1]->temperature)->toBe(1706)
        ->and($readings[Probe::FOOD1]->set_point)->toBe(2030)
        ->and($readings[Probe::FAN_OUTPUT]->temperature)->toBe(35)
        ->and($readings[Probe::FAN_OUTPUT]->set_point)->toBeNull();
});

it('does not read the device for ended or future cooks', function () {
    Cook::factory()->ended()->create();
    Cook::factory()->create(['started_at' => now()->addHour()]);

    $this->artisan('cooks:run')
        ->expectsOutput('No cooks in progress.')
        ->assertSuccessful();

    Http::assertNothingSent();
    expect(Reading::count())->toBe(0);
});

it('still reads a cook whose end time is in the future', function () {
    Cook::factory()->create(['ended_at' => now()->addHour()]);

    $this->artisan('cooks:run')->assertSuccessful();

    expect(Reading::count())->toBe(4);
});

it('asks each CyberQ only once when it has several cooks running', function () {
    $first = Cook::factory()->create();
    Cook::factory()->create(['guru_id' => $first->guru_id]);

    $this->artisan('cooks:run')->assertSuccessful();

    Http::assertSentCount(1);
    expect(Reading::count())->toBe(8);
});

it('carries on quietly when the CyberQ is switched off', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    Cook::factory()->create();

    $this->artisan('cooks:run')
        ->expectsOutputToContain('Could not talk to the CyberQ')
        ->assertSuccessful();

    expect(Reading::count())->toBe(0);
});

it('only records the probes chosen for the cook, plus the fan', function () {
    $cook = Cook::factory()->create();
    $cook->useProbes([Probe::PIT]);

    $this->artisan('cooks:run')->assertSuccessful();

    expect($cook->readings()->with('probe')->get()->pluck('probe.identifier')->sort()->values()->all())
        ->toBe(['COOK_RAMP', Probe::PIT, Probe::FAN_OUTPUT]);
});

it('picks up a probe added part way through a cook', function () {
    $cook = Cook::factory()->create();
    $cook->useProbes([Probe::PIT]);
    $this->artisan('cooks:run');

    $cook->useProbes([Probe::PIT, Probe::FOOD1]);
    $this->artisan('cooks:run');

    expect($cook->readings()->whereRelation('probe', 'identifier', Probe::FOOD1)->count())->toBe(1);
});
