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
        $this->files->ensureDirectoryExists($outputPath);

        $exitCode = $this->artisan->call('scribe:generate', [
            '--no-interaction' => true,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('Scribe failed with exit code '.$exitCode.'.');
        }

        $configuredSource = config('laravel-docs.api.generated_path');

        if (is_string($configuredSource) && $configuredSource !== '' && $this->files->isDirectory($configuredSource)) {
            $this->files->copyDirectory($configuredSource, $outputPath);
        }
    }
}
