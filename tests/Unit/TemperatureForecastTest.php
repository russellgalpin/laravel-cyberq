<?php

use App\Support\TemperatureForecast;
use Illuminate\Support\Carbon;

function climbing(float $start, float $perHour, int $readings = 10): TemperatureForecast
{
    $startedAt = Carbon::parse('2026-10-04 10:00');

    return new TemperatureForecast(collect(range(0, $readings - 1))->map(fn (int $minute) => [
        'at' => $startedAt->copy()->addMinutes($minute * 5),
        'fahrenheit' => $start + $perHour * ($minute * 5 / 60),
    ]));
}

it('estimates the time to reach a target while the food is climbing', function () {
    $forecast = climbing(150, perHour: 12);

    expect(round($forecast->risePerHour(), 3))->toBe(12.0)
        ->and($forecast->timeToReach(203)->totalMinutes)->toEqual(220.0);
});

it('makes no estimate during a stall', function () {
    expect(climbing(160, perHour: 0.2)->timeToReach(203))->toBeNull();
});

it('makes no estimate once the target is reached', function () {
    expect(climbing(200, perHour: 10)->timeToReach(203))->toBeNull();
});

it('makes no estimate from too few readings', function () {
    expect(climbing(150, perHour: 12, readings: 3)->timeToReach(203))->toBeNull();
});
