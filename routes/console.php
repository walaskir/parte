<?php

use Illuminate\Support\Facades\Schedule;

// Scheduler: stahování parte v pracovní dny (Po-Pá) v 16:00
Schedule::command('parte:download --all')
    ->dailyAt('16:00')
    ->weekdays()
    ->name('download-death-notices')
    ->onOneServer()
    ->withoutOverlapping();
