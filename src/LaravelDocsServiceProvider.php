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
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/laravel-docs.php' => config_path('laravel-docs.php'),
        ], ['laravel-docs', 'laravel-docs-config']);

        $this->commands([
            GenerateDocsCommand::class,
        ]);
    }
}
