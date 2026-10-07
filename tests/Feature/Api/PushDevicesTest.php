<?php

use App\Enums\ApnsEnvironment;
use App\Models\PushDevice;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('registers a phone for every kind of alert by default', function () {
    $this->putJson('/api/v1/push-devices', ['token' => str_repeat('A', 64), 'environment' => 'production'])
        ->assertOk()
        ->assertExactJson(['data' => ['pit_alerts' => true, 'food_alerts' => true, 'offline_alerts' => true]]);

    expect(PushDevice::sole())
        ->token->toBe(str_repeat('a', 64))
        ->environment->toBe(ApnsEnvironment::Production)
        ->user_id->toBe($this->user->id);
});

it('updates which alerts a phone wants', function () {
    $device = PushDevice::factory()->create(['user_id' => $this->user->id]);

    $this->putJson('/api/v1/push-devices', ['token' => $device->token, 'environment' => 'development', 'food_alerts' => false])
        ->assertJsonPath('data.food_alerts', false)
        ->assertJsonPath('data.pit_alerts', true);

    expect(PushDevice::count())->toBe(1);
});

it('validates the registration', function () {
    $this->putJson('/api/v1/push-devices', ['token' => 'zzz', 'environment' => 'staging', 'pit_alerts' => 'maybe'])
        ->assertJsonValidationErrors(['token', 'environment', 'pit_alerts']);
});

it('unregisters only the user\'s own phones', function () {
    $mine = PushDevice::factory()->create(['user_id' => $this->user->id]);
    $theirs = PushDevice::factory()->create();

    $this->deleteJson("/api/v1/push-devices/{$mine->token}")->assertNoContent();
    $this->deleteJson("/api/v1/push-devices/{$theirs->token}")->assertNoContent();

    expect(PushDevice::pluck('id')->all())->toBe([$theirs->id]);
});
