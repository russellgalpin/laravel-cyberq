<?php

use App\Models\Cook;
use App\Models\Probe;
use App\Support\CookSummary;

beforeEach(function () {
    $this->cook = Cook::factory()->create(['started_at' => now()->startOfMinute()->subHours(5)]);
});

it('summarises the pit', function () {
    recordReadings($this->cook, Probe::PIT, [
        0 => [2000, 2250],
        1 => [2200, 2250],
        2 => [2300, 2250],
        3 => [2600, 2250],
    ]);

    $summary = CookSummary::for($this->cook);

    expect($summary->averagePit())->toBe(227.5)
        ->and($summary->minimumPit())->toBe(200.0)
        ->and($summary->maximumPit())->toBe(260.0)
        ->and($summary->pitStability())->toBe(50.0);
});

it('leaves the pit cooling down at the end out of a finished cook', function () {
    $readings = collect(range(0, 60))->mapWithKeys(fn (int $minute) => [$minute => [2500, 2500]])->all();
    recordReadings($this->cook, Probe::PIT, $readings + [61 => [1800, 2500], 62 => [1200, 2500]]);
    recordReadings($this->cook, Probe::FAN_OUTPUT, [0 => [40, null], 60 => [40, null], 62 => [100, null]]);

    $this->cook->end();
    $summary = CookSummary::for($this->cook);

    expect($summary->averagePit())->toBe(250.0)
        ->and($summary->minimumPit())->toBe(250.0)
        ->and($summary->averageFanOutput())->toBe(40.0);
});

it('works out fan output and when each food probe hit its target', function () {
    recordReadings($this->cook, Probe::FAN_OUTPUT, [0 => [100, null], 1 => [20, null]]);
    recordReadings($this->cook, Probe::FOOD1, [0 => [1500, 2030], 120 => [2040, 2030], 180 => [2080, 2030]]);

    $summary = CookSummary::for($this->cook);

    expect($summary->averageFanOutput())->toBe(60.0)
        ->and($summary->peakTemperature(Probe::FOOD1))->toBe(208.0)
        ->and($summary->reachedTargetAt(Probe::FOOD1)->equalTo($this->cook->started_at->copy()->addHours(2)))->toBeTrue()
        ->and($summary->reachedTargetAt(Probe::FOOD2))->toBeNull();
});

it('has nothing to say about a cook without readings', function () {
    $summary = CookSummary::for($this->cook);

    expect($summary->averagePit())->toBeNull()
        ->and($summary->pitStability())->toBeNull()
        ->and($summary->averageFanOutput())->toBeNull()
        ->and($summary->peakTemperature(Probe::FOOD1))->toBeNull();
});
