<?php

namespace App\Providers;

use App\Jobs\BlingSyncJob;
use App\Jobs\RefreshTrackingsJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
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
                // Runs every 2 hours to gradually process all orders
                // This prevents timeout and allows handling 60k+ orders
                // DISPATCHES to queue instead of running directly (prevents cron blocking)
                $schedule->call(function () {
                    BlingSyncJob::dispatch(
                        forceUpdate: false, 
                        accountType: null, 
                        incrementalSync: true,
                        maxPagesPerExecution: 20  // 20 pages = ~2000 orders max per run (reduced to lower queue load)
                    )->onQueue('default');
                })
                    ->everyTwoHours() // Changed from hourly to every 2 hours to reduce load
                    ->name('bling-sync')
                    ->withoutOverlapping(180) // Expires after 180 minutes - auto-releases if job hangs
                    ->onFailure(function () {
                        Log::error('Bling sync scheduled task failed');
                    })
                    ->description('Bling incremental sync (paginated, queued)');

                // Refresh tracking for orders with invoices (last 7 days, runs twice daily)
                // Reduced scope from 30 days to 7 days to lower queue load
                // Runs twice daily instead of once to process in smaller batches
                $schedule->job(new RefreshTrackingsJob())
                    ->twiceDaily(6, 18) // At 6:00 and 18:00 - spreads load throughout the day
                    ->name('refresh-trackings')
                    ->withoutOverlapping(240) // Expires after 240 minutes
                    ->onFailure(function () {
                        Log::error('Tracking refresh scheduled task failed');
                    })
                    ->description('Refresh tracking data for recent orders (last 7 days)');
            });
        }
    }
}
