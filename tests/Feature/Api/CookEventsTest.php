<?php

use App\Models\Cook;
use App\Models\Probe;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
    $this->freezeSecond();
});

it('lists the moments that mattered, each with the temperatures at the time', function () {
    $cook = Cook::factory()->create(['started_at' => now()->subHours(10)->startOfMinute()]);
    $cook->useProbes([Probe::PIT, Probe::FOOD1], [Probe::FOOD1 => ['meat' => 'pulled_pork', 'name' => 'Big shoulder']]);

    recordReadings($cook, Probe::PIT, [1 => [2400, 2750], 60 => [2750, 2750], 302 => [2700, 2300], 305 => [2650, 2250], 360 => [2300, 2250], 420 => [2250, 2250], 430 => [2250, 2250], 440 => [2250, 2250], 450 => [2250, 2250], 460 => [2250, 2250], 470 => [2250, 2250], 480 => [2250, 2250], 505 => [2250, 2250]]);
    recordReadings($cook, Probe::FOOD1, [1 => [450, 2030], 60 => [800, 2030], 300 => [1490, 2030], 360 => [1520, 2030], 500 => [2032, 2030], 505 => [2040, 2030]]);

    $cook->wrap(Probe::FOOD1, $cook->started_at->copy()->addMinutes(300));
    $cook->noteWrap(Probe::FOOD1, 'Bark not quite set');
    $cook->notes()->create(['body' => 'Pit ran away, closed the vents', 'noted_at' => $cook->started_at->copy()->addMinutes(360)]);
    $cook->update(['ended_at' => $cook->started_at->copy()->addMinutes(510)]);

    $response = $this->getJson("/api/v1/cooks/{$cook->id}/events")->assertOk();

    expect($response->json('data.*.type'))->toBe(['started', 'wrapped', 'pit_target', 'note', 'done', 'ended'])
        ->and($response->json('data.0'))->toMatchArray([
            'title' => 'Cook started',
            'detail' => 'Pit target 275°F',
            'temperatures' => [
                ['identifier' => Probe::PIT, 'label' => 'Pit', 'temperature' => 240],
                ['identifier' => Probe::FOOD1, 'label' => 'Big shoulder', 'temperature' => 45],
            ],
        ])
        ->and($response->json('data.1'))->toMatchArray(['title' => 'Big shoulder wrapped', 'detail' => 'At 149°F', 'probe' => Probe::FOOD1, 'note' => 'Bark not quite set'])
        ->and($response->json('data.2'))->toMatchArray(['title' => 'Pit target changed', 'detail' => '275°F to 225°F'])
        ->and($response->json('data.3'))->toMatchArray(['title' => 'Note', 'note' => 'Pit ran away, closed the vents'])
        ->and($response->json('data.3.note_id'))->toBe($cook->notes()->first()->id)
        ->and($response->json('data.3.temperatures.0.temperature'))->toEqual(230)
        ->and($response->json('data.4'))->toMatchArray(['title' => 'Big shoulder done', 'detail' => 'Reached 203°F'])
        ->and($response->json('data.5.title'))->toBe('Cook ended');
});

it('tells two joints of the same meat apart', function () {
    $cook = Cook::factory()->create(['started_at' => now()->subHours(3)]);
    $cook->useProbes([Probe::PIT, Probe::FOOD1, Probe::FOOD2], [Probe::FOOD1 => ['meat' => 'pulled_pork'], Probe::FOOD2 => ['meat' => 'pulled_pork']]);
    $cook->wrap(Probe::FOOD2, now()->subHour());

    expect($this->getJson("/api/v1/cooks/{$cook->id}/events")->json('data.1.title'))->toBe('Pulled pork (Food 2) wrapped');
});

it('does not count a probe knocked hot for a moment as done', function () {
    $cook = Cook::factory()->create(['started_at' => now()->subHours(3)]);
    $cook->useProbes([Probe::PIT, Probe::FOOD1]);

    recordReadings($cook, Probe::FOOD1, [1 => [1400, 2030], 2 => [2100, 2030], 3 => [1410, 2030]]);

    expect($this->getJson("/api/v1/cooks/{$cook->id}/events")->json('data.*.type'))->toBe(['started']);
});
