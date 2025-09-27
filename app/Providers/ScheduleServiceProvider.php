<?php

namespace App\Providers;

use App\Jobs\ImportWooCommerceOrdersJob;
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
                
                // Import orders from WooCommerce every 10 minutes
                $schedule->job(new ImportWooCommerceOrdersJob('collmepro'))
                    ->everyTenMinutes()
                    ->withoutOverlapping();

                $schedule->job(new ImportWooCommerceOrdersJob('collmepro_wholesale'))
                    ->everyTenMinutes()
                    ->withoutOverlapping();
            });
        }
    }
}
