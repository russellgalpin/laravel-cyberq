<?php

use App\Models\Cook;
use App\Models\Probe;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function readingFor(Cook $cook, string $at): Reading
{
    return Reading::factory()->create([
        'cook_id' => $cook->id,
        'probe_id' => $cook->guru->probes->firstWhere('identifier', Probe::PIT)->id,
        'created_at' => $at,
    ]);
}

beforeEach(function () {
    $this->freezeSecond();
});

it('ends a cook at its last reading once the CyberQ has been silent for 12 hours', function () {
    $user = User::factory()->create();
    $cook = Cook::factory()->create(['started_at' => now()->subDay()]);
    readingFor($cook, now()->subHours(14));
    $lastReading = readingFor($cook, now()->subHours(13));

    $this->artisan('cooks:end-abandoned')
        ->expectsOutputToContain('Ended 1 abandoned cooks.')
        ->assertSuccessful();

    $cook->refresh();

    expect($cook->ended_at->equalTo($lastReading->created_at))->toBeTrue()
        ->and($cook->ended_automatically)->toBeTrue()
        ->and($cook->in_progress)->toBeFalse()
        ->and($user->notifications)->toHaveCount(1)
        ->and($user->notifications->first()->data['title'])->toBe("{$cook->name} was ended automatically");
});

it('leaves a cook alone while readings are still arriving', function () {
    $cook = Cook::factory()->create(['started_at' => now()->subDay()]);
    readingFor($cook, now()->subHours(11));

    $this->artisan('cooks:end-abandoned')->assertSuccessful();

    expect($cook->refresh()->ended_at)->toBeNull();
});

it('ends a cook that never got a reading 12 hours after it started', function () {
    $forgotten = Cook::factory()->create(['started_at' => now()->subHours(13)]);
    $justStarted = Cook::factory()->create(['started_at' => now()->subHours(2)]);

    $this->artisan('cooks:end-abandoned')->assertSuccessful();

    expect($forgotten->refresh()->ended_at->equalTo($forgotten->started_at))->toBeTrue()
        ->and($justStarted->refresh()->ended_at)->toBeNull();
});

it('uses the configured number of hours', function () {
    config(['services.cyberq.abandoned_cook_hours' => 2]);

    $cook = Cook::factory()->create(['started_at' => now()->subHours(5)]);
    readingFor($cook, now()->subHours(3));

    $this->artisan('cooks:end-abandoned')->assertSuccessful();

    expect($cook->refresh()->ended_automatically)->toBeTrue();
});

it('does not touch cooks that were ended by hand', function () {
    $cook = Cook::factory()->ended()->create();
    $endedAt = $cook->ended_at;

    $this->artisan('cooks:end-abandoned')->assertSuccessful();

    expect($cook->refresh()->ended_at->equalTo($endedAt))->toBeTrue()
        ->and($cook->ended_automatically)->toBeFalse();
});

it('stops the CyberQ being read for a cook once it has been ended', function () {
    Http::fake();
    Cook::factory()->create(['started_at' => now()->subHours(13)]);

    $this->artisan('cooks:end-abandoned')->assertSuccessful();
    $this->artisan('cooks:run')->expectsOutput('No cooks in progress.');

    Http::assertNothingSent();
});
