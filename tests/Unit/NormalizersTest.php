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

it('fills missing phpdocumentor member types from source imports', function () {
    $files = app(Filesystem::class);
    $basePath = sys_get_temp_dir().'/laravel-docs-code-source-'.uniqid();
    $rawPath = $basePath.'/raw';
    $sourcePath = $basePath.'/app';

    $files->ensureDirectoryExists($rawPath);
    $files->ensureDirectoryExists($sourcePath.'/Actions/Fortify');
    config()->set('laravel-docs.code.paths', [$sourcePath]);

    $files->put($sourcePath.'/Actions/Fortify/CreateNewUser.php', <<<'PHP'
<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Http\Request;

class CreateNewUser
{
    public function __construct(private Request $request) {}

    public function create(array $input): User {}
}
PHP);

    $files->put($rawPath.'/structure.xml', <<<'XML'
<project>
  <file path="Actions/Fortify/CreateNewUser.php">
    <class namespace="\App\Actions\Fortify" line="8">
      <name>CreateNewUser</name>
      <full_name>\App\Actions\Fortify\CreateNewUser</full_name>
      <property namespace="\App\Actions\Fortify\CreateNewUser" line="10" visibility="private">
        <name>request</name>
        <full_name>\App\Actions\Fortify\CreateNewUser::$request</full_name>
      </property>
      <method visibility="public" line="10">
        <name>__construct</name>
        <argument line="10"><name>request</name></argument>
      </method>
      <method visibility="public" line="12">
        <name>create</name>
        <argument><name>input</name><type>array</type></argument>
      </method>
    </class>
  </file>
</project>
XML);

    $normalized = app(CodeNormalizer::class)->normalize($rawPath);
    $type = $normalized['classes'][0];

    expect($type['properties'][0]['type'])->toBe('\Illuminate\Http\Request')
        ->and($type['methods'][1]['return_type'])->toBe('\App\Models\User');
});

it('normalizes enum backing types and case values', function () {
    $files = app(Filesystem::class);
    $basePath = sys_get_temp_dir().'/laravel-docs-code-enum-'.uniqid();
    $rawPath = $basePath.'/raw';
    $sourcePath = $basePath.'/app';

    $files->ensureDirectoryExists($rawPath);
    $files->ensureDirectoryExists($sourcePath.'/Enums');
    config()->set('laravel-docs.code.paths', [$sourcePath]);

    $files->put($sourcePath.'/Enums/EntityType.php', <<<'PHP'
<?php

namespace App\Enums;

enum EntityType: string
{
    // Organization
    case Company = 'company';
    case BusinessUnit = 'business_unit';

    // Commercial
    case Channel = 'channel';
}
PHP);

    $files->put($rawPath.'/structure.xml', <<<'XML'
<project>
  <file path="Enums/EntityType.php">
    <enum namespace="\App\Enums" line="5">
      <name>EntityType</name>
      <full_name>\App\Enums\EntityType</full_name>
      <case line="8">
        <name>Company</name>
        <full_name>\App\Enums\EntityType::Company</full_name>
        <value>&#039;company&#039;</value>
      </case>
      <case line="9">
        <name>BusinessUnit</name>
        <full_name>\App\Enums\EntityType::BusinessUnit</full_name>
        <value>&#039;business_unit&#039;</value>
      </case>
      <case line="12">
        <name>Channel</name>
        <full_name>\App\Enums\EntityType::Channel</full_name>
        <value>&#039;channel&#039;</value>
      </case>
    </enum>
  </file>
</project>
XML);

    $normalized = app(CodeNormalizer::class)->normalize($rawPath);
    $type = $normalized['classes'][0];

    expect($type['type'])->toBe('enum')
        ->and($type['backing_type'])->toBe('string')
        ->and($type['cases'])->toHaveCount(3)
        ->and($type['cases'][0])->toMatchArray([
            'name' => 'Company',
            'value' => "'company'",
            'summary' => 'Organization',
            'line' => 8,
        ])
        ->and($type['cases'][2])->toMatchArray([
            'name' => 'Channel',
            'value' => "'channel'",
            'summary' => 'Commercial',
            'line' => 12,
        ]);
});

it('reads database documentation from laravel schema metadata', function () {
    $files = app(Filesystem::class);
    $sourcePath = sys_get_temp_dir().'/laravel-docs-db-models-'.uniqid();

    $files->ensureDirectoryExists($sourcePath.'/Models');
    config()->set('laravel-docs.code.paths', [$sourcePath]);
    $files->put($sourcePath.'/Models/Asset.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $serial_number Human-readable asset serial number.
 */
class Asset extends Model
{
}
PHP);

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
    $assetColumns = collect($normalized['tables'][0]['columns']);

    expect($normalized['tables'][0]['name'])->toBe('assets')
        ->and($normalized['tables'][0]['model'])->toBe('Asset')
        ->and($normalized['tables'][0]['model_full_name'])->toBe('\App\Models\Asset')
        ->and($normalized['tables'][0]['primary_keys'])->toBe(['id'])
        ->and($assetColumns->firstWhere('name', 'id'))->toMatchArray([
            'name' => 'id',
            'primary' => true,
            'model_type' => 'int',
        ])
        ->and($assetColumns->firstWhere('name', 'assigned_user_id')['name'])->toBe('assigned_user_id')
        ->and($assetColumns->firstWhere('name', 'serial_number'))->toMatchArray([
            'name' => 'serial_number',
            'description' => 'Human-readable asset serial number.',
            'model_type' => 'string',
        ])
        ->and($normalized['relationships'][0])->toMatchArray([
            'from_table' => 'assets',
            'from_column' => 'assigned_user_id',
            'to_table' => 'users',
            'to_column' => 'id',
        ]);
});
