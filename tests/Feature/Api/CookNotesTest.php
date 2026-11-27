<?php

use App\Models\Cook;
use App\Models\Probe;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
    $this->freezeSecond();
    $this->cook = Cook::factory()->create(['started_at' => now()->subHours(4)]);
    $this->cook->useProbes([Probe::PIT, Probe::FOOD1]);
});

it('leaves a note now', function () {
    $this->postJson("/api/v1/cooks/{$this->cook->id}/notes", ['body' => '  Opened the vents a touch  '])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Opened the vents a touch')
        ->assertJsonPath('data.noted_at', now()->toIso8601String());

    expect($this->cook->notes()->count())->toBe(1);
});

it('leaves a note from earlier in the cook', function () {
    $this->postJson("/api/v1/cooks/{$this->cook->id}/notes", ['body' => 'Added more charcoal', 'noted_at' => now()->subHour()->toIso8601String()])
        ->assertCreated()
        ->assertJsonPath('data.noted_at', now()->subHour()->toIso8601String());
});

it('only takes a note with words in it, from during the cook', function () {
    $this->postJson("/api/v1/cooks/{$this->cook->id}/notes", ['body' => ''])->assertJsonValidationErrors('body');
    $this->postJson("/api/v1/cooks/{$this->cook->id}/notes", ['body' => 'Hi', 'noted_at' => now()->addHour()->toIso8601String()])->assertJsonValidationErrors('noted_at');
    $this->postJson("/api/v1/cooks/{$this->cook->id}/notes", ['body' => 'Hi', 'noted_at' => now()->subHours(5)->toIso8601String()])->assertJsonValidationErrors('noted_at');
});

it('edits and deletes a note', function () {
    $note = $this->cook->notes()->create(['body' => 'Pit too hot', 'noted_at' => now()->subHour()]);

    $this->patchJson("/api/v1/cooks/{$this->cook->id}/notes/{$note->id}", ['body' => 'Pit ran away after wrapping'])
        ->assertOk()
        ->assertJsonPath('data.body', 'Pit ran away after wrapping')
        ->assertJsonPath('data.noted_at', now()->subHour()->toIso8601String());

    $this->deleteJson("/api/v1/cooks/{$this->cook->id}/notes/{$note->id}")->assertNoContent();

    expect($this->cook->notes()->count())->toBe(0);
});

it('only touches a note through its own cook', function () {
    $other = Cook::factory()->create(['started_at' => now()->subHours(2)]);
    $note = $other->notes()->create(['body' => 'Not this one', 'noted_at' => now()]);

    $this->deleteJson("/api/v1/cooks/{$this->cook->id}/notes/{$note->id}")->assertNotFound();
});

it('keeps a note with a wrap, until the wrap is taken back', function () {
    $this->putJson("/api/v1/cooks/{$this->cook->id}/wraps/".Probe::FOOD1, ['note' => 'Wrapped a bit early, bark still soft'])
        ->assertOk()
        ->assertJsonPath('data.foods.0.wrap_note', 'Wrapped a bit early, bark still soft');

    $this->putJson("/api/v1/cooks/{$this->cook->id}/wraps/".Probe::FOOD1, ['wrapped_at' => now()->subMinutes(5)->toIso8601String()])
        ->assertJsonPath('data.foods.0.wrap_note', 'Wrapped a bit early, bark still soft');

    $this->deleteJson("/api/v1/cooks/{$this->cook->id}/wraps/".Probe::FOOD1)
        ->assertJsonPath('data.foods.0.wrap_note', null);
});
