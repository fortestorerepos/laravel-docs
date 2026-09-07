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

- configure `laravel-docs` as a development-time orchestrator for phpDocumentor, Scribe, and SchemaSpy

## Workflow

### 1. Confirm the Laravel app context

- confirm the target project is a Laravel application
- inspect whether Scribe, phpDocumentor, and SchemaSpy are installed or intentionally disabled
- inspect `config/laravel-docs.php` when present

### 2. Install and publish configuration

Install the package as a development dependency:

```bash
composer require --dev fab-magalhaes/laravel-docs
```

Publish the config:

```bash
php artisan vendor:publish --tag=laravel-docs-config
```

### 3. Configure documentation sections

Use `config/laravel-docs.php` to set:

- `sections.api`, `sections.database`, and `sections.code`
- `code.executable` and `code.paths`
- `api.generated_path` when Scribe writes structured output outside `public/docs`
- `database.java_executable`, `database.schemaspy_jar`, and `database.arguments`
- output paths under `storage/app/laravel-docs`

Disable any section whose external tool is not installed yet.

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

## Rules, References, and Templates

Read before executing:

- `config/laravel-docs.php`
- `src/LaravelDocsServiceProvider.php`
- `src/Console/Commands/GenerateDocsCommand.php`
- `README.md`

## Examples

- Disable database docs while SchemaSpy is not configured:

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

- do not use Laravel Docs as a replacement parser for phpDocumentor, Scribe, or SchemaSpy
- do not require a web route, queue, Redis, database table, or authentication to view generated docs
- do not point consumers at raw generated HTML from upstream tools
- do not document package internals as part of the consuming app API
