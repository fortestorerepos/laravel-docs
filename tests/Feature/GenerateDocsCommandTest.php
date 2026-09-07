<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use LaravelDocs\LaravelDocs\LaravelDocs;
use LaravelDocs\LaravelDocs\LaravelDocsServiceProvider;

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
        ])
        ->and(config('laravel-docs.code.link_external_docs'))->toBeTrue();
});

it('registers and publishes package views', function () {
    $publishPaths = LaravelDocsServiceProvider::pathsToPublish(LaravelDocsServiceProvider::class, 'laravel-docs-views');
    $publishSources = array_map(
        fn (string $path): string|false => realpath($path),
        array_keys($publishPaths),
    );

    expect(view()->exists('laravel-docs::static.index'))->toBeTrue()
        ->and($publishSources)->toContain(realpath(__DIR__.'/../../resources/views'));
});

it('can disable external documentation links in generated metadata', function () {
    $files = app(Filesystem::class);
    $basePath = sys_get_temp_dir().'/laravel-docs-test-'.uniqid();

    config()->set('laravel-docs.code.link_external_docs', false);
    config()->set('laravel-docs.base_path', $basePath);
    config()->set('laravel-docs.raw_path', $basePath.'/raw');
    config()->set('laravel-docs.normalized_path', $basePath.'/normalized');
    config()->set('laravel-docs.output_path', $basePath.'/generated');

    $files->ensureDirectoryExists($basePath.'/raw/phpdocumentor');
    $files->ensureDirectoryExists($basePath.'/raw/scribe');
    $files->put($basePath.'/raw/phpdocumentor/structure.xml', '<project />');
    $files->put($basePath.'/raw/scribe/collection.json', '{}');

    $this->artisan('docs:generate', ['--skip-tools' => true])->assertSuccessful();

    expect($files->get($basePath.'/generated/code.html'))->toContain('"enabled":false');
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
  <file path="Services/AssetService.php">
  <class final="true" abstract="false" namespace="\App\Services" line="12">
    <name>AssetService</name>
    <full_name>\App\Services\AssetService</full_name>
    <docblock><description>Handles asset operations.</description></docblock>
    <property namespace="\App\Services\AssetService" line="14" visibility="private">
      <name>repository</name>
      <full_name>\App\Services\AssetService::$repository</full_name>
      <type>\App\Repositories\AssetRepository</type>
      <docblock><description>Stores assets.</description></docblock>
    </property>
    <property namespace="\App\Services\AssetService" line="18" visibility="private">
      <name>request</name>
      <full_name>\App\Services\AssetService::$request</full_name>
      <docblock><description></description></docblock>
    </property>
    <method visibility="public" static="false" final="false" line="18">
      <name>__construct</name>
      <full_name>\App\Services\AssetService::__construct()</full_name>
      <argument line="18"><name>request</name><type>\Illuminate\Http\Request</type></argument>
      <docblock><description></description></docblock>
    </method>
    <method visibility="public" static="false" final="false" line="22">
      <name>assign</name>
      <full_name>\App\Services\AssetService::assign()</full_name>
      <argument><name>user</name><type>App\Models\User</type></argument>
      <argument><name>asset</name><type>App\Models\Asset</type></argument>
      <docblock>
        <description>Assigns an asset to a user.</description>
        <tag name="return" type="App\Models\AssetAssignment" description="The created assignment." />
      </docblock>
    </method>
  </class>
  </file>
  <file path="Contracts/AssignsAssets.php">
  <interface namespace="\App\Contracts">
    <name>AssignsAssets</name>
    <full_name>\App\Contracts\AssignsAssets</full_name>
    <docblock><description>Assigns assets to users.</description></docblock>
  </interface>
  </file>
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

    Schema::create('password_reset_tokens', function ($table) {
        $table->string('email')->primary();
        $table->string('token');
        $table->timestamp('created_at')->nullable();
    });

    $this->artisan('docs:generate', ['--skip-tools' => true])
        ->expectsOutputToContain('Laravel documentation generated.')
        ->assertSuccessful();

    expect($files->exists($basePath.'/normalized/code.json'))->toBeTrue()
        ->and($files->exists($basePath.'/normalized/api.json'))->toBeTrue()
        ->and($files->exists($basePath.'/normalized/database.json'))->toBeTrue()
        ->and($files->exists($basePath.'/generated/index.html'))->toBeTrue()
        ->and($files->exists($basePath.'/generated/api.html'))->toBeTrue()
        ->and($files->exists($basePath.'/generated/database.html'))->toBeTrue()
        ->and($files->exists($basePath.'/generated/code.html'))->toBeTrue()
        ->and($files->exists($basePath.'/generated/assets/index.css'))->toBeTrue()
        ->and($files->exists($basePath.'/generated/assets/index.js'))->toBeTrue();

    $html = $files->get($basePath.'/generated/index.html');
    $databaseHtml = $files->get($basePath.'/generated/database.html');
    $javascript = $files->get($basePath.'/generated/assets/index.js');
    $css = $files->get($basePath.'/generated/assets/index.css');
    $code = json_decode($files->get($basePath.'/normalized/code.json'), true);
    $database = json_decode($files->get($basePath.'/normalized/database.json'), true);
    $assetService = collect($code['classes'])->firstWhere('name', 'AssetService');
    $assignsAssets = collect($code['classes'])->firstWhere('name', 'AssignsAssets');
    $assignMethod = collect($assetService['methods'])->firstWhere('name', 'assign');
    $requestProperty = collect($assetService['properties'])->firstWhere('name', 'request');

    expect($html)->toContain(
        'href="api.html"',
        'href="database.html"',
        'href="code.html"',
        'data-active-tab="api"',
        '<link rel="stylesheet" href="assets/index.css">',
        '<script src="assets/index.js"></script>',
        '"enabled":true',
        'laravel_api_version',
    )
        ->and($html)->not->toContain(
            '#code',
            '<style>',
            'function renderSidebar',
        )
        ->and($databaseHtml)->toContain('data-active-tab="database"')
        ->and($javascript)->toContain(
            'namespace-group',
            'renderGroupedSidebar',
            "group:'Tables'",
            'group.badge ? typeBadge(group.badge) : \'\'',
            'title="${esc(label)}"',
            'aria-label="${esc(label)}"',
            'const list = value => Array.isArray(value) ? value : Object.values(value || {});',
            'list(tableInfo.indexes).map',
            "table(['Column','References'],list(tableInfo.foreign_keys).map",
            'activateDatabaseReference',
            'activateCodeReference',
            'dbModelLink(tableInfo)',
            'dbReferenceLink(k.references_table,k.references_column)',
            "memberId('column', c.name)",
            'codeSidebarSwitcher',
            'databaseDiagramEntries',
            'group:null',
            'databaseRelationEntries',
            'group:\'Relations\'',
            'database-relations-canvas',
            'drawDatabaseDiagram',
            'drawTableBox',
            'setCodeGroupBy(\'types\')',
            'typeGroup(type.type)',
            "interface:'Interfaces'",
            'memberId',
            'codeToc',
            'propertyDetails',
            'methodDetails',
            'methodTocName(item)',
            'methodSummaryLink(item)',
            'externalTypeUrl',
            'externalDocs.enabled === false',
            'api.laravel.com/docs',
            'displayReference(value)',
            'id="${memberId(\'method\', method.name)}"',
            'id="${memberId(\'property\', property.name)}"',
            'Return values',
        )
        ->not->toContain(
            'databaseSidebarSwitcher',
            'setDatabaseGroupBy',
            "groupType:'table'",
            "table:'T'",
        )
        ->and($css)->toContain(
            '.sidebar-switcher',
            '.sidebar-switcher button.active',
            '.code-page',
            '.on-page',
            '.column-anchor',
            '.diagram-shell',
            '.top-item',
            '.member-card',
        )
        ->and($assignMethod['line'])->toBe(22)
        ->and($assignMethod['return_description'])->toBe('The created assignment.')
        ->and($assetService['properties'][0]['visibility'])->toBe('private')
        ->and($assetService['properties'][0]['line'])->toBe(14)
        ->and($requestProperty['type'])->toBe('\Illuminate\Http\Request')
        ->and($assetService['file'])->toBe('Services/AssetService.php')
        ->and($assetService['final'])->toBeTrue()
        ->and($assignsAssets['type'])->toBe('interface')
        ->and($database['tables'])->sequence(
            fn ($table) => $table->name->toBe('assets'),
            fn ($table) => $table->name->toBe('categories'),
            fn ($table) => $table->name->toBe('password_reset_tokens'),
        );
});
