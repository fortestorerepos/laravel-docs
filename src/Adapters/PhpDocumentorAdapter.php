<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs\Adapters;

use Illuminate\Filesystem\Filesystem;
use LaravelDocs\LaravelDocs\Adapters\Concerns\FindsExecutables;
use RuntimeException;
use Symfony\Component\Process\Process;

class PhpDocumentorAdapter
{
    use FindsExecutables;

    public function __construct(private readonly Filesystem $files) {}

    public function generate(): void
    {
        $outputPath = (string) config('laravel-docs.raw_path').'/phpdocumentor';
        $paths = array_values((array) config('laravel-docs.code.paths', [app_path()]));
        $executable = $this->findExecutable(
            config('laravel-docs.code.executable'),
            'phpDocumentor',
            'laravel-docs.code.executable',
        );

        $this->files->ensureDirectoryExists($outputPath);

        $command = [$executable, '--template=xml', '-t', $outputPath];

        foreach ($paths as $path) {
            $command[] = '-d';
            $command[] = (string) $path;
        }

        $this->run($command, base_path(), 'phpDocumentor');
    }

    /**
     * @param  list<string>  $command
     */
    private function run(array $command, string $workingDirectory, string $toolName): void
    {
        $process = new Process($command, $workingDirectory);
        $process->setTimeout((float) config('laravel-docs.process_timeout', 300));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException($toolName.' failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }
}
