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
                
                // Import orders from WooCommerce Collmepro every 15 minutes
                $schedule->job(new ImportWooCommerceOrdersJob('collmepro'))
                    ->everyMinute()
                    ->withoutOverlapping()
                    ->description('Import orders from Collmepro WooCommerce');

                // Import orders from WooCommerce Wholesale every 15 minutes
                $schedule->job(new ImportWooCommerceOrdersJob('collmepro_wholesale'))
                    ->everyMinute()
                    ->withoutOverlapping()
                    ->description('Import orders from Collmepro Wholesale WooCommerce');

                // Import orders from Shopify Collme Beauty every 15 minutes
                // Uncomment when ready to use
                // $schedule->job(new ImportShopifyOrdersJob('collme_beauty'))
                //     ->everyFifteenMinutes()
                //     ->withoutOverlapping()
                //     ->description('Import orders from Collme Beauty Shopify');

                $schedule->job(new ReprocessPendingInvoicesJob())
                    ->everyMinute()
                    ->withoutOverlapping()
                    ->description('Reprocess pending invoices (status 5 → 6)');

                // Busca rastreios faltantes para pedidos que têm NFE mas não têm tracking
                $schedule->job(new FetchMissingTrackingJob())
                    ->everyMinute()
                    ->withoutOverlapping()
                    ->description('Fetch missing tracking codes for orders with NFE');

                // Atualiza status de rastreamento para pedidos que já têm tracking code
                // Roda a cada 2 horas para não sobrecarregar a API
                $schedule->job(new UpdateTrackingStatusJob())
                    ->everyMinute()
                    ->withoutOverlapping()
                    ->description('Update tracking status for orders with tracking codes');
            });
        }
    }
}
