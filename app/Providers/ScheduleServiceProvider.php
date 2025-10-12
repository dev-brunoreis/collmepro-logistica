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
                
                // Sync completo 1x por semana aos domingos às 3h da manhã
                // Para garantir que todos os pedidos sejam eventualmente sincronizados
            //     $schedule->job(new BlingSyncJob(forceUpdate: false, accountType: null, incrementalSync: false))
            //         ->weeklyOn(0, '03:00') // Domingo às 3h
            //         ->withoutOverlapping()
            //         ->description('Bling full sync (all orders) weekly on Sunday at 3am');
            });
        }
    }
}
