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
                
                // Sync incremental a cada 30 minutos (últimos 30 dias)
                // Pedidos mais antigos que 30 dias são automaticamente ignorados
                $schedule->job(new BlingSyncJob(forceUpdate: false, accountType: null, incrementalSync: true))
                    ->everyThirtyMinutes()
                    ->withoutOverlapping()
                    ->description('Bling incremental sync (last 30 days) every 30 minutes');
            });
        }
    }
}
