<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\RefreshBlingTokensJob;
use App\Jobs\SendHourlyStatsJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automatic Bling token refresh every 4 hours
Schedule::job(new RefreshBlingTokensJob)->everyFourHours();