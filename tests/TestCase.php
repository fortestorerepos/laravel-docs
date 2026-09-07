<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs\Tests;

use LaravelDocs\LaravelDocs\LaravelDocsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelDocsServiceProvider::class,
        ];
    }
}
