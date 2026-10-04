<?php

use App\Models\Cook;
use App\Models\Probe;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
    $this->freezeSecond();
});

it('says when nothing is cooking', function () {
    $this->getJson('/api/v1/dashboard')->assertOk()->assertExactJson(['data' => null]);
});

it('reports the current cook', function () {
    $cook = Cook::factory()->create(['started_at' => now()->subHours(4)]);
    recordReadings($cook, Probe::PIT, [239 => [2500, 2250]]);
    recordReadings($cook, Probe::FAN_OUTPUT, [239 => [35, null]]);
    recordReadings($cook, Probe::FOOD1, collect(range(0, 6))->mapWithKeys(fn ($step) => [200 + $step * 5 => [1500 + $step * 10, 2030]])->all());

    $this->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.cook.id', $cook->id)
        ->assertJsonPath('data.stale', false)
        ->assertJsonPath('data.fan_output', 35)
        ->assertJsonPath('data.probes.0.identifier', Probe::PIT)
        ->assertJsonPath('data.probes.0.temperature', 250)
        ->assertJsonPath('data.probes.0.pit_health', 'warning')
        ->assertJsonPath('data.probes.1.identifier', Probe::FOOD1)
        ->assertJsonPath('data.probes.1.reached_target', false)
        ->assertJsonPath('data.probes.1.minutes_to_target', fn (int $minutes) => $minutes > 0);
});

it('flags a CyberQ that has gone quiet', function () {
    $cook = Cook::factory()->create();
    recordReadings($cook, Probe::PIT, [10 => [2257, 2250]]);

    $this->getJson('/api/v1/dashboard')->assertJsonPath('data.stale', true);
});
