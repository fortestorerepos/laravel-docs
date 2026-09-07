<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use LaravelDocs\LaravelDocs\LaravelDocs;

it('resolves the singleton', function () {
    expect(app(LaravelDocs::class))->toBeInstanceOf(LaravelDocs::class)
        ->and(app(LaravelDocs::class))->toBe(app(LaravelDocs::class));
});

it('merges the package config', function () {
    expect(config('laravel-docs.output_path'))->toEndWith('laravel-docs/generated')
        ->and(config('laravel-docs.sections'))->toMatchArray([
            'api' => true,
            'database' => true,
            'code' => true,
        ]);
});

it('generates normalized json and a static html site from existing raw outputs', function () {
    $files = app(Filesystem::class);
    $basePath = sys_get_temp_dir().'/laravel-docs-test-'.uniqid();

    config()->set('laravel-docs.base_path', $basePath);
    config()->set('laravel-docs.raw_path', $basePath.'/raw');
    config()->set('laravel-docs.normalized_path', $basePath.'/normalized');
    config()->set('laravel-docs.output_path', $basePath.'/generated');

    $files->ensureDirectoryExists($basePath.'/raw/phpdocumentor');
    $files->ensureDirectoryExists($basePath.'/raw/scribe');

    $files->put($basePath.'/raw/phpdocumentor/structure.xml', <<<'XML'
<project>
  <class namespace="\App\Services">
    <name>AssetService</name>
    <full_name>\App\Services\AssetService</full_name>
    <docblock><description>Handles asset operations.</description></docblock>
    <method visibility="public">
      <name>assign</name>
      <argument><name>user</name><type>App\Models\User</type></argument>
      <argument><name>asset</name><type>App\Models\Asset</type></argument>
      <docblock>
        <description>Assigns an asset to a user.</description>
        <tag name="return" type="App\Models\AssetAssignment" />
      </docblock>
    </method>
  </class>
</project>
XML);

    $files->put($basePath.'/raw/scribe/collection.json', json_encode([
        'item' => [
            [
                'name' => 'Assets',
                'item' => [
                    [
                        'name' => 'List assets',
                        'request' => [
                            'method' => 'GET',
                            'url' => ['raw' => '/api/assets'],
                        ],
                        'parameters' => [
                            ['name' => 'status', 'type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
    ]));

    Schema::dropIfExists('assets');
    Schema::dropIfExists('categories');

    Schema::create('categories', function ($table) {
        $table->id();
    });

    Schema::create('assets', function ($table) {
        $table->id();
        $table->foreignId('category_id')->nullable()->constrained();
        $table->string('serial_number')->index();
    });

    $this->artisan('docs:generate', ['--skip-tools' => true])
        ->expectsOutputToContain('Laravel documentation generated.')
        ->assertSuccessful();

    expect($files->exists($basePath.'/normalized/code.json'))->toBeTrue()
        ->and($files->exists($basePath.'/normalized/api.json'))->toBeTrue()
        ->and($files->exists($basePath.'/normalized/database.json'))->toBeTrue()
        ->and($files->exists($basePath.'/generated/index.html'))->toBeTrue();

    $html = $files->get($basePath.'/generated/index.html');
    $code = json_decode($files->get($basePath.'/normalized/code.json'), true);
    $database = json_decode($files->get($basePath.'/normalized/database.json'), true);

    expect($html)->toContain('data-tab="api"', 'data-tab="database"', 'data-tab="code"')
        ->and($code['classes'][0]['name'])->toBe('AssetService')
        ->and($code['classes'][0]['methods'][0]['name'])->toBe('assign')
        ->and($database['tables'])->sequence(
            fn ($table) => $table->name->toBe('assets'),
            fn ($table) => $table->name->toBe('categories'),
        );
});
