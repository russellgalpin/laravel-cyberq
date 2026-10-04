<?php

use App\Models\Cook;
use App\Models\Guru;
use App\Models\Probe;
use App\Models\Reading;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
    $this->freezeSecond();
});

it('lists cooks newest first, with paging and search', function () {
    Cook::factory()->ended()->create(['name' => 'Brisket', 'started_at' => now()->subDays(20)]);
    $pork = Cook::factory()->create(['name' => 'Pork shoulder']);

    $this->getJson('/api/v1/cooks')
        ->assertOk()
        ->assertJsonPath('data.0.id', $pork->id)
        ->assertJsonPath('data.0.in_progress', true)
        ->assertJsonPath('data.1.name', 'Brisket')
        ->assertJsonPath('meta.total', 2);

    $this->getJson('/api/v1/cooks?search=bris')->assertJsonCount(1, 'data');
});

it('starts a cook now with the pit and first food probe unless told otherwise', function () {
    $guru = Guru::factory()->withProbes()->create();

    $this->postJson('/api/v1/cooks', ['name' => 'Ribs', 'guru_id' => $guru->id])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Ribs')
        ->assertJsonPath('data.in_progress', true)
        ->assertJsonPath('data.probes', [Probe::PIT, Probe::FOOD1]);

    expect(Cook::current()->started_at->equalTo(now()))->toBeTrue();
});

it('starts a cook with the probes chosen in the app', function () {
    $guru = Guru::factory()->withProbes()->create();

    $this->postJson('/api/v1/cooks', ['name' => 'Two briskets', 'guru_id' => $guru->id, 'probes' => [Probe::FOOD2, Probe::FOOD1]])
        ->assertCreated()
        ->assertJsonPath('data.probes', [Probe::FOOD1, Probe::FOOD2]);
});

it('changes the probes during a cook', function () {
    $cook = Cook::factory()->create();
    $cook->useProbes([Probe::PIT]);

    $this->patchJson("/api/v1/cooks/{$cook->id}", ['probes' => [Probe::PIT, Probe::FOOD3]])
        ->assertOk()
        ->assertJsonPath('data.probes', [Probe::PIT, Probe::FOOD3]);

    $this->patchJson("/api/v1/cooks/{$cook->id}", ['name' => 'Renamed'])
        ->assertJsonPath('data.probes', [Probe::PIT, Probe::FOOD3]);
});

it('only accepts real probes, and at least one', function () {
    $guru = Guru::factory()->withProbes()->create();

    $this->postJson('/api/v1/cooks', ['name' => 'Ribs', 'guru_id' => $guru->id, 'probes' => []])->assertJsonValidationErrors('probes');
    $this->postJson('/api/v1/cooks', ['name' => 'Ribs', 'guru_id' => $guru->id, 'probes' => ['OUTPUT_PERCENT', Probe::PIT, Probe::PIT]])
        ->assertJsonValidationErrors(['probes.0', 'probes.1']);
});

it('validates a new cook', function () {
    $this->postJson('/api/v1/cooks', ['guru_id' => 999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'guru_id']);
});

it('shows a cook with its summary', function () {
    $cook = Cook::factory()->ended()->create();
    recordReadings($cook, Probe::PIT, [0 => [2200, 2250], 60 => [2300, 2250]]);
    recordReadings($cook, Probe::FOOD1, [0 => [500, 2030], 60 => [2040, 2030]]);

    $this->getJson("/api/v1/cooks/{$cook->id}")
        ->assertOk()
        ->assertJsonPath('data.duration_minutes', 1440)
        ->assertJsonPath('data.summary.average_pit', 225)
        ->assertJsonPath('data.summary.pit_on_target_percent', 100)
        ->assertJsonPath('data.summary.food_probes.0.identifier', Probe::FOOD1)
        ->assertJsonPath('data.summary.food_probes.0.peak', 204)
        ->assertJsonPath('data.summary.food_probes.0.reached_target_at', $cook->started_at->copy()->addHour()->toIso8601String());
});

it('renames a cook and corrects its times', function () {
    $cook = Cook::factory()->ended()->create();

    $this->patchJson("/api/v1/cooks/{$cook->id}", ['name' => 'Renamed', 'ended_at' => $cook->started_at->copy()->addHours(5)->toIso8601String()])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed');

    $this->patchJson("/api/v1/cooks/{$cook->id}", ['ended_at' => $cook->started_at->copy()->subHour()->toIso8601String()])
        ->assertJsonValidationErrors('ended_at');
});

it('ends a cook in progress', function () {
    $cook = Cook::factory()->create();

    $this->postJson("/api/v1/cooks/{$cook->id}/end")
        ->assertOk()
        ->assertJsonPath('data.in_progress', false);

    $this->postJson("/api/v1/cooks/{$cook->id}/end")->assertUnprocessable();
});

it('deletes a cook and its readings', function () {
    $cook = Cook::factory()->ended()->create();
    recordReadings($cook, Probe::PIT, [0 => [2000, 2250]]);

    $this->deleteJson("/api/v1/cooks/{$cook->id}")->assertNoContent();

    expect(Cook::count())->toBe(0)->and(Reading::count())->toBe(0);
});

it('serves the graph data for a cook', function () {
    $cook = Cook::factory()->create();
    recordReadings($cook, Probe::PIT, [0 => [2000, 2250], 1 => [2100, 2250]]);
    recordReadings($cook, Probe::FAN_OUTPUT, [0 => [80, null]]);

    $this->getJson("/api/v1/cooks/{$cook->id}/timeline")
        ->assertOk()
        ->assertJsonCount(1, 'data.probes')
        ->assertJsonPath('data.probes.0.label', 'Pit')
        ->assertJsonPath('data.probes.0.temperatures.1', ['t' => $cook->started_at->copy()->addMinute()->getTimestampMs(), 'v' => 210])
        ->assertJsonPath('data.probes.0.targets.0.v', 225)
        ->assertJsonPath('data.fan_output.0.v', 80);
});

it('compares cooks by hours into the cook', function () {
    $brisket = Cook::factory()->ended()->create(['name' => 'Brisket', 'started_at' => now()->subDays(10)]);
    recordReadings($brisket, Probe::FOOD1, [0 => [400, null], 120 => [1500, null]]);

    $this->getJson("/api/v1/cook-comparisons?cooks[]={$brisket->id}&probe=FOOD1_TEMP")
        ->assertOk()
        ->assertJsonPath('data.label', 'Food 1')
        ->assertJsonPath('data.cooks.0.points', [['h' => 0, 'v' => 40], ['h' => 2, 'v' => 150]]);

    $this->getJson('/api/v1/cook-comparisons?cooks[]=999&probe=WIFI_KEY')->assertJsonValidationErrors(['cooks.0', 'probe']);
});

it('lists the CyberQs to cook on, with their probes', function () {
    Guru::factory()->withProbes()->create(['name' => 'Home']);

    $this->getJson('/api/v1/gurus')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Home')
        ->assertJsonPath('data.0.probes.*.identifier', Probe::TEMPERATURES)
        ->assertJsonPath('data.0.probes.0', ['identifier' => Probe::PIT, 'label' => 'Pit', 'in_use_by_default' => true])
        ->assertJsonPath('data.0.probes.2.in_use_by_default', false)
        ->assertJsonMissingPath('data.0.password');
});
