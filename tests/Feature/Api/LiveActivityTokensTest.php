<?php

use App\Enums\ApnsEnvironment;
use App\Enums\LiveActivityTokenKind;
use App\Models\Cook;
use App\Models\LiveActivityToken;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('registers the push token for a cook\'s Live Activity', function () {
    $cook = Cook::factory()->create();

    $this->putJson('/api/v1/live-activity-tokens', ['token' => 'ABCDEF0123456789ABCDEF0123456789', 'kind' => 'update', 'environment' => 'development', 'cook_id' => $cook->id])
        ->assertNoContent();

    expect(LiveActivityToken::sole())
        ->token->toBe('abcdef0123456789abcdef0123456789')
        ->kind->toBe(LiveActivityTokenKind::Update)
        ->environment->toBe(ApnsEnvironment::Development)
        ->cook_id->toBe($cook->id)
        ->user_id->toBe($this->user->id);
});

it('keeps only the latest start token for each phone environment', function () {
    $this->putJson('/api/v1/live-activity-tokens', ['token' => str_repeat('a', 64), 'kind' => 'start', 'environment' => 'production'])->assertNoContent();
    $this->putJson('/api/v1/live-activity-tokens', ['token' => str_repeat('b', 64), 'kind' => 'start', 'environment' => 'production'])->assertNoContent();

    expect(LiveActivityToken::sole())->token->toBe(str_repeat('b', 64))->cook_id->toBeNull();
});

it('validates tokens', function () {
    $this->putJson('/api/v1/live-activity-tokens', ['token' => 'not hex!', 'kind' => 'update', 'environment' => 'staging'])
        ->assertJsonValidationErrors(['token', 'environment', 'cook_id']);
});

it('removes only the user\'s own tokens', function () {
    $mine = LiveActivityToken::factory()->create(['user_id' => $this->user->id, 'cook_id' => Cook::factory()->create()->id]);
    $theirs = LiveActivityToken::factory()->start()->create();

    $this->deleteJson("/api/v1/live-activity-tokens/{$mine->token}")->assertNoContent();
    $this->deleteJson("/api/v1/live-activity-tokens/{$theirs->token}")->assertNoContent();

    expect(LiveActivityToken::pluck('id')->all())->toBe([$theirs->id]);
});
