<?php

use App\Enums\Meat;
use App\Models\Cook;
use App\Models\Guru;
use App\Models\Probe;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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
        ->assertJsonPath('data.ended_automatically', false)
        ->assertJsonPath('data.probes', [Probe::PIT, Probe::FOOD1]);

    expect(Cook::current()->started_at->equalTo(now()))->toBeTrue();
});

it('starts a cook with the probes chosen in the app', function () {
    $guru = Guru::factory()->withProbes()->create();

    $this->postJson('/api/v1/cooks', ['name' => 'Two briskets', 'guru_id' => $guru->id, 'probes' => [Probe::FOOD2, Probe::FOOD1]])
        ->assertCreated()
        ->assertJsonPath('data.probes', [Probe::FOOD1, Probe::FOOD2]);
});

it('lists the meats a cook can start from, with their suggested temperatures', function () {
    $this->getJson('/api/v1/meats')
        ->assertOk()
        ->assertJsonCount(count(Meat::cases()), 'data')
        ->assertJsonPath('data.0', ['value' => 'pulled_pork', 'label' => 'Pulled pork', 'pit_target' => 250, 'food_target' => 203]);
});

it('starts a cook with a different meat on each food probe, and sends each one\'s target', function () {
    Http::fake();
    $guru = Guru::factory()->withProbes()->create();

    $this->postJson('/api/v1/cooks', [
        'guru_id' => $guru->id,
        'probes' => [
            Probe::PIT,
            ['identifier' => Probe::FOOD1, 'meat' => 'pulled_pork', 'target' => 203],
            ['identifier' => Probe::FOOD3, 'meat' => 'brisket', 'name' => 'Point end', 'target' => 198],
        ],
        'pit_target' => 260,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Pulled pork and brisket')
        ->assertJsonPath('data.probes', [Probe::PIT, Probe::FOOD1, Probe::FOOD3])
        ->assertJsonPath('data.foods.0', ['identifier' => Probe::FOOD1, 'label' => 'Food 1', 'meat' => 'pulled_pork', 'meat_label' => 'Pulled pork', 'name' => null, 'display_name' => 'Pulled pork'])
        ->assertJsonPath('data.foods.1.display_name', 'Point end')
        ->assertJsonMissingPath('meta.warning');

    expect(Cook::current()->probeMeat(Probe::FOOD3))->toBe(Meat::Brisket);

    Http::assertSent(fn (Request $request) => $request->url() === 'http://cyberq.test/'
        && $request['COOK_SET'] == 260
        && $request['FOOD1_SET'] == 203
        && $request['FOOD3_SET'] == 198
        && ! isset($request['FOOD2_SET']));
});

it('still takes one done temperature for every food probe from older apps', function () {
    Http::fake();
    $guru = Guru::factory()->withProbes()->create();

    $this->postJson('/api/v1/cooks', ['name' => 'Ribs', 'guru_id' => $guru->id, 'probes' => [Probe::PIT, Probe::FOOD1, Probe::FOOD2], 'food_target' => 198])
        ->assertCreated();

    Http::assertSent(fn (Request $request) => $request['FOOD1_SET'] == 198 && $request['FOOD2_SET'] == 198 && ! isset($request['COOK_SET']));
});

it('leaves the CyberQ alone when a cook starts without targets', function () {
    Http::fake();
    $guru = Guru::factory()->withProbes()->create();

    $this->postJson('/api/v1/cooks', ['name' => 'Something new', 'guru_id' => $guru->id])->assertCreated();

    Http::assertNothingSent();
});

it('still starts the cook when the CyberQ does not take the targets', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));
    $guru = Guru::factory()->withProbes()->create();

    $this->postJson('/api/v1/cooks', ['name' => 'Ribs', 'guru_id' => $guru->id, 'probes' => [Probe::PIT, ['identifier' => Probe::FOOD1, 'meat' => 'pork_ribs', 'target' => 198]], 'pit_target' => 250])
        ->assertCreated()
        ->assertJsonPath('meta.warning', fn (string $warning) => str_contains($warning, 'did not take the targets'));

    expect(Cook::current()->name)->toBe('Ribs');
});

it('rejects an unknown meat and out of range targets', function () {
    $guru = Guru::factory()->withProbes()->create();

    $this->postJson('/api/v1/cooks', ['name' => 'Ribs', 'guru_id' => $guru->id, 'probes' => [['identifier' => Probe::FOOD1, 'meat' => 'unicorn', 'target' => 600]], 'pit_target' => 900, 'food_target' => 10])
        ->assertJsonValidationErrors(['probes.0.meat', 'probes.0.target', 'pit_target', 'food_target']);
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

it('changes what a food probe is in, and keeps it when an older app edits the probes', function () {
    $cook = Cook::factory()->create();
    $cook->useProbes([Probe::PIT, Probe::FOOD1]);

    $this->patchJson("/api/v1/cooks/{$cook->id}", ['probes' => [Probe::PIT, ['identifier' => Probe::FOOD1, 'meat' => 'lamb_shoulder', 'name' => 'Leg']]])
        ->assertOk()
        ->assertJsonPath('data.foods.0.meat', 'lamb_shoulder')
        ->assertJsonPath('data.foods.0.display_name', 'Leg');

    $this->patchJson("/api/v1/cooks/{$cook->id}", ['probes' => [Probe::PIT, Probe::FOOD1, Probe::FOOD2]])
        ->assertJsonPath('data.foods.0.meat', 'lamb_shoulder')
        ->assertJsonPath('data.foods.1.meat', null);
});

it('only accepts real probes, and at least one', function () {
    $guru = Guru::factory()->withProbes()->create();

    $this->postJson('/api/v1/cooks', ['name' => 'Ribs', 'guru_id' => $guru->id, 'probes' => []])->assertJsonValidationErrors('probes');
    $this->postJson('/api/v1/cooks', ['name' => 'Ribs', 'guru_id' => $guru->id, 'probes' => ['OUTPUT_PERCENT', Probe::PIT, Probe::PIT]])
        ->assertJsonValidationErrors(['probes.0.identifier', 'probes.1.identifier']);
});

it('validates a new cook', function () {
    $this->postJson('/api/v1/cooks', ['guru_id' => 999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['guru_id']);
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
