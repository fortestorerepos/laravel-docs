<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs\Adapters;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class ScribeAdapter
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Kernel $artisan,
    ) {}

    public function generate(): void
    {
        $commands = Artisan::all();

        if (! array_key_exists('scribe:generate', $commands)) {
            throw new RuntimeException('Scribe command not found. Install Scribe or disable laravel-docs.sections.api.');
        }

        $outputPath = (string) config('laravel-docs.raw_path').'/scribe';
        $scribeDir = (string) config('laravel-docs.api.scribe_dir', storage_path('app/laravel-docs/cache/scribe'));
        $this->files->ensureDirectoryExists($outputPath);
        $this->files->ensureDirectoryExists($scribeDir);

        $this->configureScribeOutput($outputPath);

        $exitCode = $this->artisan->call('scribe:generate', [
            '--force' => true,
            '--no-interaction' => true,
            '--scribe-dir' => $scribeDir,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('Scribe failed with exit code '.$exitCode.'.');
        }

        $configuredSource = config('laravel-docs.api.generated_path');

        if (
            is_string($configuredSource)
            && $configuredSource !== ''
            && realpath($configuredSource) !== realpath($outputPath)
            && $this->files->isDirectory($configuredSource)
        ) {
            $this->files->copyDirectory($configuredSource, $outputPath);
        }
    }

    private function configureScribeOutput(string $outputPath): void
    {
        config()->set('scribe.type', 'static');
        config()->set('scribe.static.output_path', $outputPath);
        config()->set('scribe.postman.enabled', true);
        config()->set('scribe.openapi.enabled', true);
        config()->set('scribe.laravel.add_routes', false);
    }
}
