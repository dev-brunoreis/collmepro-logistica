<?php

namespace App\Providers;

use App\Jobs\ImportWooCommerceOrdersJob;
use App\Jobs\ImportShopifyOrdersJob;
use App\Jobs\SyncAllBlingDataJob;
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
                    ->everyMinute()
                    ->withoutOverlapping();

                $schedule->job(new ImportWooCommerceOrdersJob('collmepro_wholesale'))
                    ->everyMinute()
                    ->withoutOverlapping();

                // Import orders from Shopify every 5 minutes
                $schedule->job(new ImportShopifyOrdersJob('collme_beauty'))
                    ->everyMinute()
                    ->withoutOverlapping();

                // Sync Bling NFE data every 15 minutes
                $schedule->job(new SyncAllBlingDataJob())
                    ->everyMinute()
                    ->withoutOverlapping();
            });
        }
    }
}
