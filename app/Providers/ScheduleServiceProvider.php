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
                
                // Sync incremental a cada 2 horas (últimos 30 dias)
                // Para sync completo inicial, usar: php artisan bling:sync --full
                // Pedidos mais antigos que 30 dias são automaticamente ignorados em sync incremental
                $schedule->job(new BlingSyncJob(forceUpdate: false, accountType: null, incrementalSync: true))
                    ->everyTwoHours()
                    ->withoutOverlapping()
                    ->description('Bling incremental sync (last 30 days) every 2 hours');
            });
        }
    }
}
