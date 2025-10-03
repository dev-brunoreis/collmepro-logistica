<?php

namespace App\Providers;

use App\Jobs\ImportWooCommerceOrdersJob;
use App\Jobs\ImportShopifyOrdersJob;
use App\Jobs\ReprocessPendingInvoicesJob;
use App\Jobs\FetchMissingTrackingJob;
use App\Jobs\UpdateTrackingStatusJob;
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
                
                $schedule->job(new ImportWooCommerceOrdersJob('collmepro'))
                    ->everyMinute()
                    ->withoutOverlapping()
                    ->description('Import orders from Collmepro WooCommerce every minute');

                $schedule->job(new ImportWooCommerceOrdersJob('collmepro_atacadista'))
                    ->everyMinute()
                    ->withoutOverlapping()
                    ->description('Import orders from Collmepro Atacadista WooCommerce every minute');

                $schedule->job(new ImportShopifyOrdersJob('collme_beauty'))
                    ->everyMinute()
                    ->withoutOverlapping()
                    ->description('Import orders from Collme Beauty Shopify every minute');

                $schedule->job(new ReprocessPendingInvoicesJob())
                    ->everyMinute()
                    ->withoutOverlapping()
                    ->description('Reprocess pending invoices (status 5 → 6) every minute');

                $schedule->job(new FetchMissingTrackingJob())
                    ->everyMinute()
                    ->withoutOverlapping()
                    ->description('Fetch missing tracking codes for orders with NFE every minute');

                $schedule->job(new UpdateTrackingStatusJob())
                    ->everyMinute()
                    ->withoutOverlapping()
                    ->description('Update tracking status for orders with tracking codes every minute');
            });
        }
    }
}
