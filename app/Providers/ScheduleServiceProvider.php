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
                
                // Sync with chunking: Process 10 pages (1000 orders) per execution
                // Runs every 15 minutes to gradually process all orders
                // This prevents timeout and allows handling 60k+ orders
                $schedule->call(function () {
                    $job = new BlingSyncJob(
                        forceUpdate: false, 
                        accountType: null, 
                        incrementalSync: false,
                        maxPagesPerExecution: 20  // 10 pages = ~1000 orders max per run
                    );
                    $job->handle();
                })
                    ->everyFifteenMinutes()
                    ->name('bling-sync')
                    ->withoutOverlapping()
                    ->description('Bling incremental sync (last 30 days, chunked)');
            });
        }
    }
}
