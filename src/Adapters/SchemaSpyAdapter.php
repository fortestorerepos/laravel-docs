<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs\Adapters;

use Illuminate\Filesystem\Filesystem;
use LaravelDocs\LaravelDocs\Adapters\Concerns\FindsExecutables;
use RuntimeException;
use Symfony\Component\Process\Process;

class SchemaSpyAdapter
{
    use FindsExecutables;

    public function __construct(private readonly Filesystem $files) {}

    public function generate(): void
    {
        $outputPath = (string) config('laravel-docs.raw_path').'/schemaspy';
        $this->files->ensureDirectoryExists($outputPath);

        $java = $this->findExecutable(
            config('laravel-docs.database.java_executable'),
            'Java',
            'laravel-docs.database.java_executable'
        );

        $jar = config('laravel-docs.database.schemaspy_jar');

        if (! is_string($jar) || $jar === '' || ! is_file($jar)) {
            throw new RuntimeException('SchemaSpy jar not found. Configure laravel-docs.database.schemaspy_jar.');
        }

        $command = array_merge(
            [$java, '-jar', $jar, '-o', $outputPath],
            array_values((array) config('laravel-docs.database.arguments', []))
        );

        $process = new Process($command, base_path());
        $process->setTimeout((float) config('laravel-docs.process_timeout', 300));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('SchemaSpy failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }
}
