<?php

use App\Enums\Meat;
use App\Models\Cook;
use App\Models\Guru;
use App\Models\Probe;
use App\Support\CookSnapshot;
use App\Support\Forecasting\CookingCurve;
use App\Support\Forecasting\CookingHistory;
use App\Support\Forecasting\FoodForecast;
use App\Support\Forecasting\FoodSeries;

beforeEach(function () {
    $this->freezeSecond();
    $this->guru = Guru::factory()->withProbes()->create();
});

function finishedCook(Guru $guru, Meat $meat, float $speed = 1.0, float $pit = 250, string $identifier = Probe::FOOD1): Cook
{
    $cook = Cook::factory()->for($guru)->create(['started_at' => now()->subDays(10 + Cook::count()), 'ended_at' => now()->subDays(5)]);
    $cook->useProbes([Probe::PIT, $identifier], [$identifier => ['meat' => $meat]]);
    simulateCook($cook, $identifier, speed: $speed, pit: $pit);

    return $cook;
}

function seriesFor(Cook $cook, string $identifier = Probe::FOOD1): FoodSeries
{
    $byProbe = $cook->readings()->with('probe')->get()->groupBy('probe.identifier')
        ->map(fn ($readings) => $readings->mapWithKeys(fn ($reading) => [$reading->created_at->getTimestamp() => $reading->temperature_in_fahrenheit])->all());

    return collect(FoodSeries::segments($byProbe[$identifier], $byProbe[Probe::PIT]))->last();
}

it('splits a probe\'s readings where it was pulled out or the cook paused, ignoring empty readings', function () {
    $start = 1_800_000_000;
    $food = [$start => 50, $start + 60 => 0, $start + 120 => 52, $start + 180 => 20, $start + 240 => 22, $start + 4000 => 30];
    $pit = array_fill_keys(array_keys($food), 250.0);

    $segments = FoodSeries::segments($food, $pit);

    expect($segments)->toHaveCount(3)
        ->and(array_column($segments[0]->points, 'food'))->toBe([50, 52])
        ->and(array_column($segments[1]->points, 'food'))->toBe([20, 22])
        ->and($segments[2]->latest()['food'])->toEqual(30);
});

it('learns how fast food rises at each temperature from a finished cook', function () {
    $cook = finishedCook($this->guru, Meat::PulledPork);

    $curve = CookingCurve::fromSeries([seriesFor($cook)]);

    expect($curve->risePerHour(100, 250))->toEqualWithDelta(simulatedRisePerHour(100, 250), 2)
        ->and($curve->risePerHour(160, 250))->toEqualWithDelta(simulatedRisePerHour(160, 250), 1)
        ->and($curve->risePerHour(185, 250))->toEqualWithDelta(simulatedRisePerHour(185, 250), 1.5);
});

it('averages cooks by their typical pace, so one slow joint does not swamp the rest', function () {
    $average = CookingCurve::average([new CookingCurve([10 => 1.0, 11 => 2.0]), new CookingCurve([10 => 4.0])]);

    expect($average->bands)->toEqualWithDelta([10 => 2.0, 11 => 2.0], 0.0001)
        ->and((new CookingCurve([10 => 1.0]))->blendedWith(new CookingCurve([10 => 4.0, 12 => 3.0]), 0.5)->bands)
        ->toEqualWithDelta([10 => 2.0, 12 => 3.0], 0.0001);
});

it('measures how fast this joint is going compared with past cooks', function () {
    $history = finishedCook($this->guru, Meat::PulledPork);
    $curve = CookingCurve::fromSeries([seriesFor($history)]);

    $cook = Cook::factory()->for($this->guru)->create(['started_at' => now()->subMinutes(240)]);
    simulateCook($cook, Probe::FOOD1, speed: 2.0, minutes: 240);

    expect((new FoodForecast($curve, seriesFor($cook)))->speed())->toEqualWithDelta(2.0, 0.25);
});

it('forecasts through the stall from how past cooks went, where a straight line would be far too hopeful', function () {
    finishedCook($this->guru, Meat::PulledPork);
    finishedCook($this->guru, Meat::PulledPork, speed: 0.8);
    finishedCook($this->guru, Meat::LambShoulder, speed: 1.5, pit: 280);

    $cook = Cook::factory()->for($this->guru)->create(['started_at' => now()->subMinutes(120)]);
    $cook->useProbes([Probe::PIT, Probe::FOOD1], [Probe::FOOD1 => ['meat' => Meat::PulledPork]]);
    $wholeCook = Cook::factory()->for($this->guru)->create(['started_at' => now()->subMinutes(120)]);
    $actualMinutes = simulateCook($wholeCook, Probe::FOOD1);
    simulateCook($cook, Probe::FOOD1, minutes: 120);

    $forecast = (new CookSnapshot($cook))->probe(Probe::FOOD1)->timeToTarget;

    expect($forecast->totalMinutes)->toEqualWithDelta($actualMinutes - 120, ($actualMinutes - 120) * 0.2);
});

it('falls back to the last few readings until there are enough finished cooks', function () {
    finishedCook($this->guru, Meat::PulledPork);

    expect(CookingHistory::load()->curveFor(Meat::PulledPork))->toBeNull();

    $cook = Cook::factory()->for($this->guru)->create(['started_at' => now()->subMinutes(60)]);
    $cook->useProbes([Probe::PIT, Probe::FOOD1]);
    simulateCook($cook, Probe::FOOD1, minutes: 60);

    expect((new CookSnapshot($cook))->probe(Probe::FOOD1)->timeToTarget)->not->toBeNull();
});

it('leans on a meat\'s own cooks more the more of them there are', function () {
    finishedCook($this->guru, Meat::LambShoulder, speed: 2.0);
    finishedCook($this->guru, Meat::PulledPork, speed: 0.5);
    finishedCook($this->guru, Meat::PulledPork, speed: 0.5);

    $history = CookingHistory::fromFinishedCooks();
    $everything = $history->curveFor(null)->risePerHour(100, 250);
    $lamb = $history->curveFor(Meat::LambShoulder)->risePerHour(100, 250);
    $porkWithOneMoreCook = CookingHistory::fromArray([...$history->toArray(), ...array_slice($history->toArray(), 1, 1)])->curveFor(Meat::PulledPork)->risePerHour(100, 250);
    $pork = $history->curveFor(Meat::PulledPork)->risePerHour(100, 250);

    expect($lamb)->toBeGreaterThan($everything)
        ->and($pork)->toBeLessThan($everything)
        ->and($porkWithOneMoreCook)->toBeLessThan($pork)
        ->and($history->cookCount())->toBe(3)
        ->and($history->cookCount(Meat::PulledPork))->toBe(2);
});

it('only learns from finished cooks with readings near done', function () {
    finishedCook($this->guru, Meat::PulledPork);
    $inProgress = Cook::factory()->for($this->guru)->create(['started_at' => now()->subHours(3)]);
    simulateCook($inProgress, Probe::FOOD1, minutes: 180);
    $neverGotHot = Cook::factory()->for($this->guru)->ended()->create();
    simulateCook($neverGotHot, Probe::FOOD1, minutes: 60);

    expect(CookingHistory::fromFinishedCooks()->cookCount())->toBe(1);
});

it('relearns when a finished cook\'s meat changes', function () {
    $cook = finishedCook($this->guru, Meat::PulledPork);
    finishedCook($this->guru, Meat::PulledPork);

    expect(CookingHistory::load()->cookCount(Meat::Brisket))->toBe(0);

    $cook->useProbes([Probe::PIT, Probe::FOOD1], [Probe::FOOD1 => ['meat' => Meat::Brisket]]);

    expect(CookingHistory::load()->cookCount(Meat::Brisket))->toBe(1);
});

it('gives no forecast once the food is at its target', function () {
    $curve = new CookingCurve([20 => 2.0]);
    $series = new FoodSeries([['at' => 0, 'food' => 204.0, 'pit' => 250.0]]);

    expect((new FoodForecast($curve, $series))->timeToReach(203))->toBeNull();
});
