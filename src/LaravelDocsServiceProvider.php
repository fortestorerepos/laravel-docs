<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs;

use Illuminate\Support\ServiceProvider;
use LaravelDocs\LaravelDocs\Console\Commands\LaravelDocsCommand;

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
        $this->loadRoutesFrom(__DIR__.'/../routes/laravel-docs.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-docs');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'laravel-docs');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/laravel-docs.php' => config_path('laravel-docs.php'),
        ], ['laravel-docs', 'laravel-docs-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/laravel-docs'),
        ], ['laravel-docs', 'laravel-docs-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/laravel-docs'),
        ], ['laravel-docs', 'laravel-docs-lang']);

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/laravel-docs'),
        ], ['laravel-docs', 'laravel-docs-assets']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['laravel-docs', 'laravel-docs-migrations']);

        $this->commands([
            LaravelDocsCommand::class,
        ]);
    }
}
