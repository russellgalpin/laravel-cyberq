<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('schedules reading the CyberQ every minute and ending abandoned cooks', function () {
    $events = collect(app(Schedule::class)->events())
        ->mapWithKeys(fn (Event $event) => [str($event->command)->after("'artisan' ")->toString() => $event->expression]);

    expect($events)->toMatchArray([
        'cooks:run' => '* * * * *',
        'cooks:end-abandoned' => '*/15 * * * *',
    ]);
});
