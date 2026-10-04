<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('cooks:run')->everyMinute()->withoutOverlapping();

Schedule::command('cooks:end-abandoned')->everyFifteenMinutes();
