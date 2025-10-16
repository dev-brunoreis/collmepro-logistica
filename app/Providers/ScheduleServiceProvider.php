<?php

namespace App\Providers;

use App\Jobs\BlingSyncJob;
use App\Jobs\SendHourlyStatsJob;
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
                
                // Sync with chunking: Process 20 pages (2000 orders) per execution
                // Uses persistent pagination to gradually process all orders
                // Runs every 15 minutes to gradually process all orders
                // This prevents timeout and allows handling 60k+ orders
                $schedule->call(function () {
                    $job = new BlingSyncJob(
                        forceUpdate: false, 
                        accountType: null, 
                        incrementalSync: true,
                        maxPagesPerExecution: 20  // 20 pages = ~2000 orders max per run
                    );
                    $job->handle();
                })
                    ->everyFifteenMinutes()
                    ->name('bling-sync')
                    ->withoutOverlapping()
                    ->description('Bling full sync (paginated)');

                // Send hourly stats ping
                $schedule->job(new SendHourlyStatsJob())
                    ->hourly()
                    ->name('hourly-stats-ping')
                    ->description('Send hourly stats ping');
            });
        }
    }
}
