<?php

declare(strict_types=1);

namespace Korioinc\ExpoIntegrity;

use Illuminate\Support\ServiceProvider;

class ExpoIntegrityServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-expo-integrity.php', 'laravel-expo-integrity');

        $this->app->singleton(ExpoIntegrity::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/laravel-expo-integrity.php' => config_path('laravel-expo-integrity.php'),
        ], ['laravel-expo-integrity', 'laravel-expo-integrity-config']);
    }
}
