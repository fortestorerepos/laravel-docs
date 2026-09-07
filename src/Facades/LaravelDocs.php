<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \LaravelDocs\LaravelDocs\LaravelDocs
 */
class LaravelDocs extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \LaravelDocs\LaravelDocs\LaravelDocs::class;
    }
}
