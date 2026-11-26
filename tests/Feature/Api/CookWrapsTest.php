<?php

use App\Models\Cook;
use App\Models\Probe;
use App\Models\Reading;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
    $this->freezeSecond();
    $this->cook = Cook::factory()->create(['started_at' => now()->subHours(8)]);
    $this->cook->useProbes([Probe::PIT, Probe::FOOD1, Probe::FOOD2], [Probe::FOOD1 => ['meat' => 'pulled_pork']]);
});

it('marks a joint as wrapped now, with its temperature at the time', function () {
    Reading::create(['cook_id' => $this->cook->id, 'probe_id' => $this->cook->guru->probes->firstWhere('identifier', Probe::FOOD1)->id, 'temperature' => 1652, 'created_at' => now()->subMinute()]);

    $this->putJson("/api/v1/cooks/{$this->cook->id}/wraps/".Probe::FOOD1)
        ->assertOk()
        ->assertJsonPath('data.foods.0.wrapped_at', now()->toIso8601String())
        ->assertJsonPath('data.foods.0.wrapped_at_temperature', 165.2)
        ->assertJsonPath('data.foods.1.wrapped_at', null);

    expect($this->cook->fresh()->probeWrappedAt(Probe::FOOD1)->equalTo(now()))->toBeTrue();
});

it('marks a joint as wrapped earlier in the cook', function () {
    $this->putJson("/api/v1/cooks/{$this->cook->id}/wraps/".Probe::FOOD2, ['wrapped_at' => now()->subMinutes(20)->toIso8601String()])
        ->assertOk()
        ->assertJsonPath('data.foods.1.wrapped_at', now()->subMinutes(20)->toIso8601String());
});

it('only takes a time during the cook, for one of its food probes', function () {
    $this->putJson("/api/v1/cooks/{$this->cook->id}/wraps/".Probe::FOOD1, ['wrapped_at' => now()->addHour()->toIso8601String()])
        ->assertJsonValidationErrors('wrapped_at');

    $this->putJson("/api/v1/cooks/{$this->cook->id}/wraps/".Probe::FOOD1, ['wrapped_at' => now()->subHours(9)->toIso8601String()])
        ->assertJsonValidationErrors('wrapped_at');

    $this->putJson("/api/v1/cooks/{$this->cook->id}/wraps/".Probe::PIT)->assertJsonValidationErrors('probe');
    $this->putJson("/api/v1/cooks/{$this->cook->id}/wraps/".Probe::FOOD3)->assertJsonValidationErrors('probe');
});

it('takes a wrap back', function () {
    $this->cook->wrap(Probe::FOOD1, now()->subHour());

    $this->deleteJson("/api/v1/cooks/{$this->cook->id}/wraps/".Probe::FOOD1)
        ->assertOk()
        ->assertJsonPath('data.foods.0.wrapped_at', null);
});

it('keeps a wrap when the cook\'s probes are edited', function () {
    $this->cook->wrap(Probe::FOOD1, now()->subHour());

    $this->patchJson("/api/v1/cooks/{$this->cook->id}", ['probes' => [Probe::PIT, ['identifier' => Probe::FOOD1, 'meat' => 'pulled_pork', 'size' => 'large'], Probe::FOOD2]])
        ->assertOk()
        ->assertJsonPath('data.foods.0.wrapped_at', now()->subHour()->toIso8601String());
});
