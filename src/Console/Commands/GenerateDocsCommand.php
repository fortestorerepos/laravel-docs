<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use LaravelDocs\LaravelDocs\Adapters\LaravelSchemaAdapter;
use LaravelDocs\LaravelDocs\Adapters\PhpDocumentorAdapter;
use LaravelDocs\LaravelDocs\Adapters\ScribeAdapter;
use LaravelDocs\LaravelDocs\Generators\StaticSiteGenerator;
use LaravelDocs\LaravelDocs\Normalizers\ApiNormalizer;
use LaravelDocs\LaravelDocs\Normalizers\CodeNormalizer;
use Throwable;

class GenerateDocsCommand extends Command
{
    protected $signature = 'docs:generate {--skip-tools : Normalize existing raw outputs without running external generators}';

    protected $description = 'Generate a standalone static Laravel documentation site for API, database, and PHP code.';

    public function handle(
        Filesystem $files,
        PhpDocumentorAdapter $phpDocumentor,
        ScribeAdapter $scribe,
        LaravelSchemaAdapter $laravelSchema,
        CodeNormalizer $codeNormalizer,
        ApiNormalizer $apiNormalizer,
        StaticSiteGenerator $siteGenerator,
    ): int {
        $basePath = (string) config('laravel-docs.base_path');
        $rawPath = (string) config('laravel-docs.raw_path');
        $normalizedPath = (string) config('laravel-docs.normalized_path');
        $outputPath = (string) config('laravel-docs.output_path');

        $files->ensureDirectoryExists($rawPath);
        $files->ensureDirectoryExists($normalizedPath);
        $files->ensureDirectoryExists($outputPath);

        try {
            if (! $this->option('skip-tools')) {
                $this->runEnabledTools($phpDocumentor, $scribe);
            }

            $this->line('Normalizing documentation data...');

            $normalized = [
                'api' => $this->sectionEnabled('api') ? $apiNormalizer->normalize($rawPath.'/scribe') : ['groups' => []],
                'database' => $this->sectionEnabled('database') ? $laravelSchema->generate() : ['tables' => [], 'constraints' => [], 'relationships' => []],
                'code' => $this->sectionEnabled('code') ? $codeNormalizer->normalize($rawPath.'/phpdocumentor') : ['namespaces' => [], 'classes' => []],
            ];

            foreach ($normalized as $section => $data) {
                $files->put(
                    $normalizedPath.'/'.$section.'.json',
                    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
                );
            }

            $siteGenerator->generate($normalized, $outputPath);

            $this->info('Laravel documentation generated.');
            $this->line('Base path: '.$basePath);
            $this->line('Open: '.$outputPath.'/index.html');

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }
    }

    private function runEnabledTools(
        PhpDocumentorAdapter $phpDocumentor,
        ScribeAdapter $scribe,
    ): void {
        if ($this->sectionEnabled('code')) {
            $this->line('Generating PHP code documentation with phpDocumentor...');
            $phpDocumentor->generate();
        }

        if ($this->sectionEnabled('api')) {
            $this->line('Generating API documentation with Scribe...');
            $scribe->generate();
        }

        if ($this->sectionEnabled('database')) {
            $this->line('Reading database documentation from Laravel schema metadata...');
        }
    }

    private function sectionEnabled(string $section): bool
    {
        return (bool) config("laravel-docs.sections.{$section}", true);
    }
}
