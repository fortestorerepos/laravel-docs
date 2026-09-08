<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs\Generators;

use Composer\InstalledVersions;
use Illuminate\Contracts\View\Factory;
use Illuminate\Filesystem\Filesystem;

class StaticSiteGenerator
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Factory $views,
    ) {}

    /**
     * @param  array{api: array<string, mixed>, database: array<string, mixed>, code: array<string, mixed>}  $data
     */
    public function generate(array $data, string $outputPath): void
    {
        $this->files->ensureDirectoryExists($outputPath);
        $this->files->ensureDirectoryExists($outputPath.'/assets');

        $this->copyAsset('index.css', $outputPath);
        $this->copyAsset('index.js', $outputPath);
        $this->copyAsset('search.js', $outputPath);

        foreach ($this->pages() as $filename => $tab) {
            $this->files->put($outputPath.'/'.$filename, $this->html($data, $tab));
        }
    }

    /**
     * @param  array{api: array<string, mixed>, database: array<string, mixed>, code: array<string, mixed>}  $data
     */
    private function html(array $data, string $activeTab): string
    {
        $data['_meta'] = [
            'external_docs' => [
                'enabled' => (bool) config('laravel-docs.code.link_external_docs', true),
                'laravel_api_version' => $this->packageMajorVersion('laravel/framework')
                    ?? $this->packageMajorVersion('illuminate/support')
                    ?? 'master',
            ],
        ];

        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        return $this->views->make('laravel-docs::static.index', [
            'activeTab' => $activeTab,
            'docsJson' => $json === false ? '{}' : $json,
        ])->render();
    }

    /**
     * @return array<string, string>
     */
    private function pages(): array
    {
        return [
            'index.html' => 'api',
            'api.html' => 'api',
            'database.html' => 'database',
            'code.html' => 'code',
        ];
    }

    private function copyAsset(string $filename, string $outputPath): void
    {
        $this->files->copy($this->assetPath($filename), $outputPath.'/assets/'.$filename);
    }

    private function assetPath(string $filename): string
    {
        $publishedPath = resource_path('views/vendor/laravel-docs/static/assets/'.$filename);

        if ($this->files->exists($publishedPath)) {
            return $publishedPath;
        }

        return __DIR__.'/../../resources/views/static/assets/'.$filename;
    }

    private function packageMajorVersion(string $package): ?string
    {
        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled($package)) {
            return null;
        }

        $version = InstalledVersions::getPrettyVersion($package);

        if ($version === null || ! preg_match('/(\d+)/', $version, $matches)) {
            return null;
        }

        return $matches[1].'.x';
    }
}
