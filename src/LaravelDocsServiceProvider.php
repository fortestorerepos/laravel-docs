<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs;

use Illuminate\Support\ServiceProvider;
use LaravelDocs\LaravelDocs\Console\Commands\GenerateDocsCommand;

class LaravelDocsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-docs.php', 'laravel-docs');

        $this->app->singleton(LaravelDocs::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-docs');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/laravel-docs.php' => config_path('laravel-docs.php'),
        ], ['laravel-docs', 'laravel-docs-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/laravel-docs'),
        ], ['laravel-docs', 'laravel-docs-views']);

        $this->commands([
            GenerateDocsCommand::class,
        ]);
    }
}
