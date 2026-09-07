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
        $cachePath = (string) config('laravel-docs.code.cache_path', storage_path('app/laravel-docs/cache/phpdocumentor'));
        $paths = array_values((array) config('laravel-docs.code.paths', [app_path()]));
        $command = array_merge($this->commandPrefix(), ['run', '--template=xml', '--cache-folder', $cachePath, '-t', $outputPath]);

        $this->files->ensureDirectoryExists($outputPath);
        $this->files->ensureDirectoryExists($cachePath);

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

    /**
     * @return list<string>
     */
    private function commandPrefix(): array
    {
        try {
            return [$this->findExecutable(
                config('laravel-docs.code.executable'),
                'phpDocumentor',
                'laravel-docs.code.executable',
                ['phpdoc'],
            )];
        } catch (RuntimeException $exception) {
            if (! (bool) config('laravel-docs.code.auto_download_phar', true)) {
                throw new RuntimeException($exception->getMessage().' Enable laravel-docs.code.auto_download_phar or install phpdocumentor/phpdocumentor.');
            }

            return [PHP_BINARY, $this->ensurePhar()];
        }
    }

    private function ensurePhar(): string
    {
        $pharPath = (string) config('laravel-docs.code.phar_path');

        if ($this->files->exists($pharPath)) {
            return $pharPath;
        }

        $pharUrl = (string) config('laravel-docs.code.phar_url');

        if ($pharUrl === '') {
            throw new RuntimeException('phpDocumentor PHAR URL is not configured. Configure laravel-docs.code.phar_url.');
        }

        $this->files->ensureDirectoryExists(dirname($pharPath));

        $contents = @file_get_contents($pharUrl);

        if ($contents === false) {
            throw new RuntimeException('phpDocumentor executable not found and the PHAR could not be downloaded. Configure laravel-docs.code.executable or laravel-docs.code.phar_url.');
        }

        $this->files->put($pharPath, $contents);

        return $pharPath;
    }
}
