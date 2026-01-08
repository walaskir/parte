<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Scheduler: stahování parte v pracovní dny (Po-Pá) v 16:00
Schedule::command('parte:download --all')
    ->dailyAt('16:00')
    ->weekdays()
    ->name('download-death-notices')
    ->onOneServer()
    ->withoutOverlapping()
    ->then(function () {
        // Jednorázové zpracování extraction fronty po stažení (jen v local prostředí)
        if (app()->environment('local')) {
            Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--queue' => 'extraction,default',
                '--tries' => 3,
            ]);
        }
    });
