<?php

declare(strict_types=1);

namespace Korioinc\ExpoIntegrity;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Korioinc\ExpoIntegrity\Http\Middleware\ExpoIntegrityMiddleware;

class ExpoIntegrityServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-expo-integrity.php', 'laravel-expo-integrity');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Router $router): void
    {
        $router->aliasMiddleware('expo-integrity', ExpoIntegrityMiddleware::class);

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/laravel-expo-integrity.php' => config_path('laravel-expo-integrity.php'),
        ], ['laravel-expo-integrity', 'laravel-expo-integrity-config']);
    }
}
