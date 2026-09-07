<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use LaravelDocs\LaravelDocs\Normalizers\ApiNormalizer;
use LaravelDocs\LaravelDocs\Normalizers\DatabaseNormalizer;

it('normalizes openapi groups from tags', function () {
    $files = app(Filesystem::class);
    $path = sys_get_temp_dir().'/laravel-docs-openapi-'.uniqid();
    $files->ensureDirectoryExists($path);
    $files->put($path.'/openapi.json', json_encode([
        'openapi' => '3.0.0',
        'paths' => [
            '/api/users' => [
                'get' => [
                    'tags' => ['Users'],
                    'summary' => 'List users',
                    'responses' => ['200' => ['description' => 'OK']],
                ],
            ],
        ],
    ]));

    $normalized = app(ApiNormalizer::class)->normalize($path);

    expect($normalized['groups'][0]['name'])->toBe('Users')
        ->and($normalized['groups'][0]['endpoints'][0]['method'])->toBe('GET')
        ->and($normalized['groups'][0]['endpoints'][0]['uri'])->toBe('/api/users');
});

it('normalizes schemaspy table relationships', function () {
    $files = app(Filesystem::class);
    $path = sys_get_temp_dir().'/laravel-docs-db-'.uniqid();
    $files->ensureDirectoryExists($path);
    $files->put($path.'/database.xml', <<<'XML'
<database>
  <table name="assets">
    <column name="id" type="bigint" nullable="false" primaryKey="true" />
    <foreignKey column="assigned_user_id" referencesTable="users" referencesColumn="id" />
  </table>
</database>
XML);

    $normalized = app(DatabaseNormalizer::class)->normalize($path);

    expect($normalized['tables'][0]['name'])->toBe('assets')
        ->and($normalized['relationships'][0])->toMatchArray([
            'from_table' => 'assets',
            'from_column' => 'assigned_user_id',
            'to_table' => 'users',
            'to_column' => 'id',
        ]);
});
