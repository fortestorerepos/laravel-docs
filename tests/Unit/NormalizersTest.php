<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use LaravelDocs\LaravelDocs\Adapters\LaravelSchemaAdapter;
use LaravelDocs\LaravelDocs\Normalizers\ApiNormalizer;
use LaravelDocs\LaravelDocs\Normalizers\CodeNormalizer;

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

it('normalizes phpdocumentor child element names and return tags', function () {
    $files = app(Filesystem::class);
    $path = sys_get_temp_dir().'/laravel-docs-code-'.uniqid();
    $files->ensureDirectoryExists($path);
    $files->put($path.'/structure.xml', <<<'XML'
<project>
  <class namespace="\App\Actions\Fortify">
    <name>ResetUserPassword</name>
    <full_name>\App\Actions\Fortify\ResetUserPassword</full_name>
    <implements>\Laravel\Fortify\Contracts\ResetsUserPasswords</implements>
    <method visibility="public">
      <name>reset</name>
      <argument><name>user</name><type>\App\Models\User</type></argument>
      <argument><name>input</name><type>array&lt;string,string&gt;</type></argument>
      <docblock>
        <description>Validate and reset the user's forgotten password.</description>
        <tag name="return" type="void" />
      </docblock>
    </method>
  </class>
</project>
XML);

    $normalized = app(CodeNormalizer::class)->normalize($path);

    expect($normalized['classes'][0]['name'])->toBe('ResetUserPassword')
        ->and($normalized['classes'][0]['methods'][0]['name'])->toBe('reset')
        ->and($normalized['classes'][0]['methods'][0]['parameters'][0])->toMatchArray([
            'name' => 'user',
            'type' => '\App\Models\User',
        ])
        ->and($normalized['classes'][0]['methods'][0]['return_type'])->toBe('void');
});

it('reads database documentation from laravel schema metadata', function () {
    Schema::dropIfExists('assets');
    Schema::dropIfExists('users');

    Schema::create('users', function ($table) {
        $table->id();
    });

    Schema::create('assets', function ($table) {
        $table->id();
        $table->foreignId('assigned_user_id')->constrained('users');
        $table->string('serial_number')->unique();
    });

    $normalized = app(LaravelSchemaAdapter::class)->generate();

    expect($normalized['tables'][0]['name'])->toBe('assets')
        ->and($normalized['tables'][0]['columns'][1]['name'])->toBe('assigned_user_id')
        ->and($normalized['relationships'][0])->toMatchArray([
            'from_table' => 'assets',
            'from_column' => 'assigned_user_id',
            'to_table' => 'users',
            'to_column' => 'id',
        ]);
});
