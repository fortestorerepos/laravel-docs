<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
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
    $files->ensureDirectoryExists($basePath.'/raw/schemaspy');

    $files->put($basePath.'/raw/phpdocumentor/structure.xml', <<<'XML'
<project>
  <class name="App\Services\AssetService" namespace="App\Services">
    <description>Handles asset operations.</description>
    <method name="assign" visibility="public" return="App\Models\AssetAssignment">
      <description>Assigns an asset to a user.</description>
      <argument name="user" type="App\Models\User" />
      <argument name="asset" type="App\Models\Asset" />
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

    $files->put($basePath.'/raw/schemaspy/database.xml', <<<'XML'
<database>
  <table name="assets">
    <column name="id" type="bigint" nullable="false" primaryKey="true" />
    <column name="category_id" type="bigint" nullable="true" />
    <foreignKey name="assets_category_id_foreign" column="category_id" referencesTable="categories" referencesColumn="id" />
    <index name="assets_category_id_index" unique="false"><column name="category_id" /></index>
  </table>
</database>
XML);

    $this->artisan('docs:generate', ['--skip-tools' => true])
        ->expectsOutputToContain('Laravel documentation generated.')
        ->assertSuccessful();

    expect($files->exists($basePath.'/normalized/code.json'))->toBeTrue()
        ->and($files->exists($basePath.'/normalized/api.json'))->toBeTrue()
        ->and($files->exists($basePath.'/normalized/database.json'))->toBeTrue()
        ->and($files->exists($basePath.'/generated/index.html'))->toBeTrue();

    $html = $files->get($basePath.'/generated/index.html');
    $code = json_decode($files->get($basePath.'/normalized/code.json'), true);

    expect($html)->toContain('data-tab="api"', 'data-tab="database"', 'data-tab="code"')
        ->and($code['classes'][0]['name'])->toBe('AssetService')
        ->and($code['classes'][0]['methods'][0]['name'])->toBe('assign');
});
