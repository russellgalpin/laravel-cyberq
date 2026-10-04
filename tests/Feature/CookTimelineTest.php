<?php

use App\Models\Cook;
use App\Models\Probe;
use App\Support\CookTimeline;

beforeEach(function () {
    $this->cook = Cook::factory()->create(['started_at' => now()->startOfMinute()->subHours(3)]);
});

it('charts temperatures in degrees against millisecond timestamps', function () {
    recordReadings($this->cook, Probe::PIT, [0 => [2000, 2250], 1 => [2105, 2250]]);

    $series = (new CookTimeline($this->cook))->temperatures(Probe::PIT);

    expect($series)->toBe([
        [$this->cook->started_at->getTimestampMs(), 200.0],
        [$this->cook->started_at->copy()->addMinute()->getTimestampMs(), 210.5],
    ]);
});

it('charts targets and fan output', function () {
    recordReadings($this->cook, Probe::PIT, [0 => [2000, 2250]]);
    recordReadings($this->cook, Probe::FAN_OUTPUT, [0 => [45, null]]);

    $timeline = new CookTimeline($this->cook);

    expect($timeline->setPoints(Probe::PIT)[0][1])->toBe(225.0)
        ->and($timeline->fanOutput()[0][1])->toBe(45.0)
        ->and($timeline->setPoints(Probe::FAN_OUTPUT))->toBe([]);
});

it('averages long cooks down to a manageable number of points', function () {
    recordReadings($this->cook, Probe::PIT, collect(range(0, 99))->mapWithKeys(fn ($minute) => [$minute => [2000 + $minute * 10, 2250]])->all());

    $series = (new CookTimeline($this->cook, maxPoints: 10))->temperatures(Probe::PIT);

    expect($series)->toHaveCount(10)
        ->and($series[0][1])->toBe(204.5)
        ->and($series[9][1])->toBe(294.5);
});

it('keeps target changes sharp rather than averaging them', function () {
    recordReadings($this->cook, Probe::PIT, [0 => [2000, 2250], 1 => [2000, 2250], 2 => [2000, 2500], 3 => [2000, 2500]]);

    $series = (new CookTimeline($this->cook, maxPoints: 2))->setPoints(Probe::PIT);

    expect(array_column($series, 1))->toBe([225.0, 250.0]);
});

it('lines cooks up by hours since they started', function () {
    recordReadings($this->cook, Probe::FOOD1, [0 => [500, null], 90 => [1200, null]]);

    expect((new CookTimeline($this->cook))->temperaturesByElapsedHours(Probe::FOOD1))->toBe([[0.0, 50.0], [1.5, 120.0]]);
});

it('only includes readings from its own cook', function () {
    $other = Cook::factory()->create(['guru_id' => $this->cook->guru_id]);
    recordReadings($other, Probe::PIT, [0 => [2000, 2250]]);

    $timeline = new CookTimeline($this->cook);

    expect($timeline->hasReadingsFor(Probe::PIT))->toBeFalse()
        ->and($timeline->temperatures(Probe::PIT))->toBe([]);
});

it('stops comparing a forgotten cook once the CyberQ went quiet', function () {
    recordReadings($this->cook, Probe::FOOD1, [0 => [500, null], 60 => [1500, null], 600 => [700, null], 610 => [690, null]]);

    $timeline = new CookTimeline($this->cook);

    expect($timeline->temperaturesByElapsedHours(Probe::FOOD1))->toBe([[0.0, 50.0], [1.0, 150.0]])
        ->and($timeline->activeUntil(Probe::FOOD1)->equalTo($this->cook->started_at->copy()->addHour()))->toBeTrue();
});
