<?php

use App\Models\Cook;
use App\Models\Probe;

it('works out when finished cooks stopped cooking', function () {
    $finished = Cook::factory()->create(['started_at' => now()->startOfMinute()->subDays(2), 'ended_at' => now()->subDay()]);
    recordReadings($finished, Probe::PIT, [0 => [2500, 2500], 90 => [2500, 2500]]);
    $running = Cook::factory()->create(['started_at' => now()->startOfMinute()->subHour()]);
    recordReadings($running, Probe::PIT, [0 => [2500, 2500]]);

    $this->artisan('cooks:record-cooked-until')
        ->expectsOutputToContain('Checked 1 cooks.')
        ->assertSuccessful();

    expect($finished->refresh()->cooked_until->equalTo($finished->started_at->copy()->addMinutes(90)))->toBeTrue()
        ->and($finished->durationForHumans())->toBe('1h 30m')
        ->and($running->refresh()->cooked_until)->toBeNull();
});
