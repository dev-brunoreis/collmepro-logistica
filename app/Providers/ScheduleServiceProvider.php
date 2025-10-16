<?php

namespace App\Providers;

use App\Jobs\BlingSyncJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class ScheduleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                
                $schedule->job(new BlingSyncJob(forceUpdate: false, accountType: null, incrementalSync: false))
                    ->everyMinute()
                    ->withoutOverlapping()
                    ->description('Bling full sync (all orders) every 5 minutes');
            });
        }
    }
}
