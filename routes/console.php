<?php

use Illuminate\Support\Facades\Schedule;

// Scheduler: stahování parte každý den v 16:00
Schedule::command('parte:download --all')
    ->dailyAt('16:00')
    ->name('download-death-notices')
    ->onOneServer()
    ->withoutOverlapping();
