<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Bling services
        $this->app->singleton(\App\Services\BlingOAuthService::class);
        $this->app->singleton(\App\Services\CollmeProBlingService::class);
        $this->app->singleton(\App\Services\CollmeAtacadistaBlingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
