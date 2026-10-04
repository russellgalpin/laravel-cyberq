<?php

use App\Models\Cook;
use App\Models\Probe;
use App\Models\Reading;

it('knows which cooks are in progress', function () {
    $running = Cook::factory()->create();
    $endingLater = Cook::factory()->create(['ended_at' => now()->addHour()]);
    Cook::factory()->ended()->create();
    Cook::factory()->create(['started_at' => now()->addHour()]);

    expect(Cook::query()->active()->pluck('id')->all())->toEqualCanonicalizing([$running->id, $endingLater->id])
        ->and($running->in_progress)->toBeTrue()
        ->and($endingLater->in_progress)->toBeTrue();
});

it('does not count a cook that has not started yet as in progress', function () {
    expect(Cook::factory()->make(['started_at' => now()->addHour()])->in_progress)->toBeFalse();
});

it('picks the most recently started cook in progress as the current cook', function () {
    Cook::factory()->create(['started_at' => now()->subHours(5)]);
    $latest = Cook::factory()->create(['started_at' => now()->subHour()]);
    Cook::factory()->ended()->create(['started_at' => now()->subMinutes(10)]);

    expect(Cook::current()->is($latest))->toBeTrue();
});

it('has no current cook when nothing is in progress', function () {
    Cook::factory()->ended()->create();

    expect(Cook::current())->toBeNull();
});

it('measures how long a cook took', function () {
    $this->freezeSecond();

    $finished = Cook::factory()->make(['started_at' => now()->subHours(10), 'ended_at' => now()->subHours(2)]);
    $running = Cook::factory()->make(['started_at' => now()->subMinutes(90)]);

    expect($finished->duration()->totalHours)->toEqual(8)
        ->and($running->duration()->totalMinutes)->toEqual(90);
});

it('ends now', function () {
    $this->freezeSecond();
    $cook = Cook::factory()->create();

    $cook->end();

    expect($cook->refresh()->ended_at->equalTo(now()))->toBeTrue()
        ->and($cook->ended_automatically)->toBeFalse();
});

it('finds the latest reading for a probe', function () {
    $cook = Cook::factory()->create();
    $pit = $cook->guru->probes->firstWhere('identifier', Probe::PIT);
    Reading::factory()->create(['cook_id' => $cook->id, 'probe_id' => $pit->id, 'temperature' => 2000]);
    Reading::factory()->create(['cook_id' => $cook->id, 'probe_id' => $pit->id, 'temperature' => 2100]);

    expect($cook->latestReadingFor(Probe::PIT)->temperature_in_fahrenheit)->toBe(210.0)
        ->and($cook->latestReadingFor(Probe::FOOD1))->toBeNull();
});

it('deletes its readings along with it', function () {
    $cook = Cook::factory()->create();
    Reading::factory()->count(3)->create(['cook_id' => $cook->id, 'probe_id' => $cook->guru->probes->first()->id]);

    $cook->delete();

    expect(Reading::count())->toBe(0);
});

it('converts readings from tenths of a degree', function () {
    $reading = new Reading(['temperature' => 2257, 'set_point' => 2250]);
    $withoutTarget = new Reading(['temperature' => 35, 'set_point' => null]);

    expect($reading->temperature_in_fahrenheit)->toBe(225.7)
        ->and($reading->set_point_in_fahrenheit)->toBe(225.0)
        ->and($withoutTarget->set_point_in_fahrenheit)->toBeNull();
});

it('only maps temperature probes to a target and status', function () {
    expect(Probe::setPointKeyFor(Probe::FOOD2))->toBe('FOOD2_SET')
        ->and(Probe::statusKeyFor(Probe::PIT))->toBe('COOK_STATUS')
        ->and(Probe::setPointKeyFor(Probe::FAN_OUTPUT))->toBeNull()
        ->and(Probe::statusKeyFor('COOK_RAMP'))->toBeNull();
});

it('describes its duration to the minute', function () {
    $this->freezeSecond();

    $cook = Cook::factory()->make(['started_at' => now()->subHours(10)->subSeconds(20), 'ended_at' => now()]);

    expect($cook->durationForHumans())->toBe('10h')
        ->and($cook->durationForHumans(short: false))->toBe('10 hours');
});

it('uses every temperature probe when none were chosen', function () {
    $cook = Cook::factory()->create();

    expect($cook->probesInUse()->pluck('identifier')->all())->toBe(Probe::TEMPERATURES);
});

it('lists the chosen probes in order', function () {
    $cook = Cook::factory()->create();
    $cook->useProbes([Probe::FOOD3, Probe::PIT, 'OUTPUT_PERCENT']);

    expect($cook->probesInUse()->pluck('identifier')->all())->toBe([Probe::PIT, Probe::FOOD3]);
});
