<?php

namespace Xoco70\LaravelTournaments;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class TournamentsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @param Router $router
     *
     * @return void
     */
    public function boot()
    {
        $viewPath = __DIR__.'/../resources/views';
        $this->loadViewsFrom($viewPath, 'laravel-tournaments');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../translations', 'laravel-tournaments');

        $this->publishes([__DIR__ . '/../database/seeders' => $this->app->databasePath().'/seeders'], 'laravel-tournaments');
        $this->publishes([__DIR__.'/../database/factories' => $this->app->databasePath().'/factories'], 'laravel-tournaments');
        $this->publishes([__DIR__.'/../resources/assets' => public_path('vendor/laravel-tournaments')], 'laravel-tournaments');
        $this->publishes([__DIR__.'/../config/laravel-tournaments.php' => config_path('laravel-tournaments.php')], 'laravel-tournaments');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->make(DBHelpers::class);
    }
}
