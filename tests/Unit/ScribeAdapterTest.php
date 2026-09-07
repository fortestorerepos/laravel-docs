<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use LaravelDocs\LaravelDocs\Adapters\ScribeAdapter;
use Mockery\MockInterface;

it('runs scribe with cache and generated output inside laravel docs storage', function () {
    $files = app(Filesystem::class);
    $basePath = sys_get_temp_dir().'/laravel-docs-scribe-'.uniqid();

    config()->set('laravel-docs.raw_path', $basePath.'/raw');
    config()->set('laravel-docs.api.scribe_dir', $basePath.'/cache/scribe');
    config()->set('laravel-docs.api.generated_path', $basePath.'/raw/scribe');

    Artisan::command('scribe:generate', fn (): int => 0);

    $kernel = Mockery::mock(Kernel::class, function (MockInterface $mock) use ($basePath): void {
        $mock->shouldReceive('call')
            ->once()
            ->with('scribe:generate', [
                '--force' => true,
                '--no-interaction' => true,
                '--scribe-dir' => $basePath.'/cache/scribe',
            ])
            ->andReturn(0);
    });

    (new ScribeAdapter($files, $kernel))->generate();

    expect(config('scribe.type'))->toBe('static')
        ->and(config('scribe.static.output_path'))->toBe($basePath.'/raw/scribe')
        ->and(config('scribe.postman.enabled'))->toBeTrue()
        ->and(config('scribe.openapi.enabled'))->toBeTrue()
        ->and($files->isDirectory($basePath.'/raw/scribe'))->toBeTrue()
        ->and($files->isDirectory($basePath.'/cache/scribe'))->toBeTrue();
});
