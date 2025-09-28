<?php

namespace App\Providers;

use App\Jobs\ImportWooCommerceOrdersJob;
use App\Jobs\ImportShopifyOrdersJob;
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
                
                // Import orders from WooCommerce every 5 minutes
                $schedule->job(new ImportWooCommerceOrdersJob('collmepro'))
                    ->everyFiveMinutes()
                    ->withoutOverlapping();

                $schedule->job(new ImportWooCommerceOrdersJob('collmepro_wholesale'))
                    ->everyFiveMinutes()
                    ->withoutOverlapping();

                // Import orders from Shopify every 5 minutes
                $schedule->job(new ImportShopifyOrdersJob('collme_beauty'))
                    ->everyFiveMinutes()
                    ->withoutOverlapping();
            });
        }
    }
}
