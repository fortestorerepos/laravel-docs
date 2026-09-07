<div align="center">
    <h1>Laravel Docs</h1>
</div>

Laravel Docs is a development-time documentation generator for Laravel applications.
It orchestrates existing documentation tools, normalizes their structured output, and writes a standalone static HTML site.

The first supported sources are:

- phpDocumentor for PHP code documentation
- Scribe for API documentation
- Laravel schema metadata for database documentation

Laravel Docs does not replace these tools or parse Laravel projects from scratch.

## Installation

Install the package via Composer:

```bash
composer require --dev fab-magalhaes/laravel-docs
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=laravel-docs-config
```

Publish the static site Blade views when you want to customize the generated frontend:

```bash
php artisan vendor:publish --tag=laravel-docs-views
```

Scribe is installed with Laravel Docs. Laravel Docs forces Scribe's generated output and intermediate cache into `storage/app/laravel-docs`.
For PHP code docs, Laravel Docs first looks for `phpdoc`, then downloads the official phpDocumentor PHAR automatically when needed.

Database docs use Laravel's configured database connection and schema metadata.

## Configuration

The published `config/laravel-docs.php` file controls output paths, enabled sections, and tool locations.

```php
return [
    'output_path' => storage_path('app/laravel-docs/generated'),

    'sections' => [
        'api' => true,
        'database' => true,
        'code' => true,
    ],

    'code' => [
        'executable' => 'phpdoc',
        'auto_download_phar' => true,
        'link_external_docs' => true,
        'phar_url' => 'https://phpdoc.org/phpDocumentor.phar',
        'phar_path' => storage_path('app/laravel-docs/bin/phpDocumentor.phar'),
        'cache_path' => storage_path('app/laravel-docs/cache/phpdocumentor'),
        'paths' => [
            app_path(),
        ],
    ],

    'api' => [
        'scribe_dir' => storage_path('app/laravel-docs/cache/scribe'),
        'generated_path' => storage_path('app/laravel-docs/raw/scribe'),
    ],

    'database' => [
        'connection' => null,
    ],
];
```

Set `laravel-docs.database.connection` to a named Laravel database connection when you do not want to use the default connection.
Set `laravel-docs.code.link_external_docs` to `false` when generated code pages should avoid external framework documentation links.

## Usage

Generate documentation:

```bash
php artisan docs:generate
```

Laravel Docs writes files under:

```text
storage/app/laravel-docs/
```

The generated static site is available at:

```text
storage/app/laravel-docs/generated/index.html
```

You can open that file directly in a browser without running Laravel. The generated sections are routed as static pages: `api.html`, `database.html`, and `code.html`.

When you already have raw structured outputs and only want to rebuild the normalized JSON and static site, run:

```bash
php artisan docs:generate --skip-tools
```

## Output Layout

```text
storage/app/laravel-docs/
|-- raw/
|   |-- phpdocumentor/
|   `-- scribe/
|-- cache/
|   |-- phpdocumentor/
|   `-- scribe/
|-- normalized/
|   |-- api.json
|   |-- database.json
|   `-- code.json
`-- generated/
    |-- index.html
    |-- api.html
    |-- database.html
    |-- code.html
    `-- assets/
        |-- index.css
        `-- index.js
```

The UI is a compact static HTML page with `API`, `DB`, and `Code` tabs in the header.
Its source lives in package Blade views and assets under `resources/views/static`, so the generated frontend can be customized without editing PHP generator strings.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Laravel Docs! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Fab. Magalhaes](https://github.com/fab-magalhaes)
- [All Contributors](../../contributors)

## License

Laravel Docs is open-sourced software licensed under the [MIT license](LICENSE.md).
