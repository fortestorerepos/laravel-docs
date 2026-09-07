---
name: laravel-docs-development
description: >
  Configure and run the Laravel Docs package in Laravel applications.
license: MIT
metadata:
  author: Fab. Magalhaes
---

# Laravel Docs

Use this skill when a Laravel application needs to generate a standalone documentation site with Laravel Docs.

## Primary Goal

- configure `fab-magalhaes/laravel-docs` as a development-time orchestrator for phpDocumentor, Scribe, and Laravel schema metadata

## Workflow

### 1. Confirm the Laravel app context

- confirm the target project is a Laravel application
- inspect `config/database.php` when database documentation should use a non-default connection
- inspect `config/laravel-docs.php` when present

### 2. Install and publish configuration

Install the package as a development dependency:

```bash
composer require --dev fab-magalhaes/laravel-docs
```

Scribe is installed with Laravel Docs. Laravel Docs forces Scribe's generated output and intermediate cache into `storage/app/laravel-docs`.
Laravel Docs first looks for `phpdoc`, then downloads the official phpDocumentor PHAR automatically when needed.

Publish the config:

```bash
php artisan vendor:publish --tag=laravel-docs-config
```

Publish frontend Blade views only when the generated static site needs customization:

```bash
php artisan vendor:publish --tag=laravel-docs-views
```

### 3. Configure documentation sections

Use `config/laravel-docs.php` to set:

- `sections.api`, `sections.database`, and `sections.code`
- `api.scribe_dir` and `api.generated_path`
- `code.executable`, `code.auto_download_phar`, `code.link_external_docs`, `code.phar_url`, `code.phar_path`, `code.cache_path`, and `code.paths`
- `api.generated_path` when Scribe writes structured output outside `public/docs`
- `database.connection` when database docs should use a named Laravel connection
- output paths under `storage/app/laravel-docs`

Disable database docs when the app database is not reachable.

### 4. Generate the static site

Run:

```bash
php artisan docs:generate
```

Open:

```text
storage/app/laravel-docs/generated/index.html
```

Use this only to view the final static site; it does not require a running Laravel application.
The generated pages are rendered from package Blade views before they are written as static HTML. Section pages are available at `api.html`, `database.html`, and `code.html`, with shared `assets/index.css` and `assets/index.js`.
Use the generated header's Download PDF button to export the currently opened section: API endpoints, database diagrams/tables/constraints, or PHP code documentation.

## Rules, References, and Templates

Read before executing:

- `config/laravel-docs.php`
- `src/LaravelDocsServiceProvider.php`
- `src/Console/Commands/GenerateDocsCommand.php`
- `resources/views/static/`
- `resources/views/static/assets/`
- `README.md`

## Examples

- Disable database docs while the database connection is not available:

```php
'sections' => [
    'api' => true,
    'database' => false,
    'code' => true,
],
```

- Rebuild the static site from existing raw structured outputs:

```bash
php artisan docs:generate --skip-tools
```

## Anti-patterns

- do not use Laravel Docs as a replacement parser for phpDocumentor or Scribe
- do not require a web route, queue, Redis, database table, or authentication to view generated docs
- do not point consumers at raw generated HTML from upstream tools
- do not edit generated `index.html` directly when a persistent frontend customization belongs in published package views
- do not leave Scribe intermediate files or phpDocumentor cache in the application root
- do not document package internals as part of the consuming app API
