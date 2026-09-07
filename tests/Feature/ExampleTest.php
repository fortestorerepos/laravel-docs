<?php

declare(strict_types=1);

use LaravelDocs\LaravelDocs\LaravelDocs;

it('resolves the singleton', function () {
    expect(app(LaravelDocs::class))->toBeInstanceOf(LaravelDocs::class);
});

it('returns the same instance from the container', function () {
    expect(app(LaravelDocs::class))->toBe(app(LaravelDocs::class));
});

it('merges the package config', function () {
    expect(config('laravel-docs.placeholder'))->toBe('default');
});

it('loads the package translations', function () {
    expect(trans('laravel-docs::messages.placeholder'))->toBe('LaravelDocs placeholder translation.');
});

it('loads the package views', function () {
    expect(view()->exists('laravel-docs::placeholder'))->toBeTrue();
});

it('registers the artisan command', function () {
    $this->artisan('laravel-docs:placeholder')
        ->expectsOutputToContain('LaravelDocs placeholder command executed.')
        ->assertSuccessful();
});
