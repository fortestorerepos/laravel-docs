<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs\Adapters;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LaravelSchemaAdapter
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @return array{tables: list<array<string, mixed>>, relationships: list<array<string, string>>}
     */
    public function generate(): array
    {
        $connection = $this->connection();
        $schema = $connection->getSchemaBuilder();
        $tables = [];
        $constraints = [];
        $relationships = [];
        $models = $this->modelsByTable();

        $tableNames = $this->tableNames($schema);
        sort($tableNames);

        foreach ($tableNames as $tableName) {
            $foreignKeys = $this->foreignKeys($schema, $tableName);

            $tables[] = [
                'name' => $tableName,
                'model' => $models[$tableName]['name'] ?? '',
                'model_full_name' => $models[$tableName]['full_name'] ?? '',
                'columns' => $this->columns($schema, $tableName, $models[$tableName]['properties'] ?? []),
                'primary_keys' => $this->primaryKeys($schema, $tableName),
                'foreign_keys' => $foreignKeys,
                'indexes' => $this->indexes($schema, $tableName),
                'relationships' => array_map(
                    fn (array $key): array => [
                        'from_table' => $tableName,
                        'from_column' => $key['column'],
                        'to_table' => $key['references_table'],
                        'to_column' => $key['references_column'],
                    ],
                    $foreignKeys,
                ),
            ];

            foreach ($foreignKeys as $foreignKey) {
                $constraints[] = [
                    'name' => $foreignKey['name'],
                    'child_table' => $tableName,
                    'child_column' => $foreignKey['column'],
                    'parent_table' => $foreignKey['references_table'],
                    'parent_column' => $foreignKey['references_column'],
                    'on_update' => $foreignKey['on_update'],
                    'on_delete' => $foreignKey['on_delete'],
                ];
                $relationships[] = [
                    'name' => $foreignKey['name'],
                    'from_table' => $tableName,
                    'from_column' => $foreignKey['column'],
                    'to_table' => $foreignKey['references_table'],
                    'to_column' => $foreignKey['references_column'],
                    'on_update' => $foreignKey['on_update'],
                    'on_delete' => $foreignKey['on_delete'],
                ];
            }
        }

        return [
            'tables' => $tables,
            'constraints' => $constraints,
            'relationships' => $relationships,
        ];
    }

    private function connection(): Connection
    {
        $connection = config('laravel-docs.database.connection');

        return is_string($connection) && $connection !== ''
            ? DB::connection($connection)
            : DB::connection();
    }

    /**
     * @return list<string>
     */
    private function tableNames(Builder $schema): array
    {
        return array_values(array_filter(
            array_map(
                fn (array $table): string => $table['name'],
                $schema->getTables(),
            ),
            fn (string $table): bool => ! str_starts_with($table, 'sqlite_'),
        ));
    }

    /**
     * @param  array<string, array{type: string, description: string}>  $modelProperties
     * @return list<array{name: string, type: string, nullable: bool, default: string, primary: bool, description: string, model_type: string}>
     */
    private function columns(Builder $schema, string $tableName, array $modelProperties): array
    {
        $primaryKeys = $this->primaryKeys($schema, $tableName);

        return array_map(
            fn (array $column): array => [
                'name' => $column['name'],
                'type' => (string) $column['type_name'],
                'nullable' => (bool) $column['nullable'],
                'default' => $this->stringValue($column['default']),
                'primary' => in_array($column['name'], $primaryKeys, true),
                'description' => $this->stringValue($column['comment'] ?? '') ?: ($modelProperties[$column['name']]['description'] ?? ''),
                'model_type' => $modelProperties[$column['name']]['type'] ?? '',
            ],
            $schema->getColumns($tableName),
        );
    }

    /**
     * @return list<string>
     */
    private function primaryKeys(Builder $schema, string $tableName): array
    {
        foreach ($this->indexes($schema, $tableName) as $index) {
            if ($index['type'] === 'primary' || strtolower($index['name']) === 'primary') {
                return $index['columns'];
            }
        }

        return [];
    }

    /**
     * @return list<array{name: string, unique: bool, columns: list<string>, type: string}>
     */
    private function indexes(Builder $schema, string $tableName): array
    {
        return array_map(
            fn (array $index): array => [
                'name' => (string) $index['name'],
                'unique' => (bool) $index['unique'],
                'columns' => $index['columns'],
                'type' => (string) $index['type'],
            ],
            $schema->getIndexes($tableName),
        );
    }

    /**
     * @return list<array{name: string, column: string, references_table: string, references_column: string, on_update: string, on_delete: string}>
     */
    private function foreignKeys(Builder $schema, string $tableName): array
    {
        return array_map(
            fn (array $key): array => [
                'name' => (string) ($key['name'] ?? $this->foreignKeyName($tableName, $key['columns'][0] ?? '')),
                'column' => $key['columns'][0] ?? '',
                'references_table' => $key['foreign_table'],
                'references_column' => $key['foreign_columns'][0] ?? '',
                'on_update' => (string) ($key['on_update'] ?? ''),
                'on_delete' => (string) ($key['on_delete'] ?? ''),
            ],
            $schema->getForeignKeys($tableName),
        );
    }

    private function foreignKeyName(string $tableName, string $column): string
    {
        return $column === '' ? '' : $tableName.'_'.$column.'_foreign';
    }

    /**
     * @return array<string, array{name: string, full_name: string, properties: array<string, array{type: string, description: string}>}>
     */
    private function modelsByTable(): array
    {
        $models = [];

        foreach (config('laravel-docs.code.paths', []) as $path) {
            if (! is_string($path) || ! $this->files->isDirectory($path)) {
                continue;
            }

            foreach ($this->files->allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $source = $this->files->get($file->getPathname());

                if (! preg_match('/class\s+([a-zA-Z_][a-zA-Z0-9_]*)\s+extends\s+Model\b/', $source, $class)) {
                    continue;
                }

                $table = $this->modelTableName($source, $class[1]);
                $models[$table] = [
                    'name' => $class[1],
                    'full_name' => $this->modelFullName($source, $class[1]),
                    'properties' => array_replace($models[$table]['properties'] ?? [], $this->modelPropertyDocs($source)),
                ];
            }
        }

        return $models;
    }

    private function modelFullName(string $source, string $class): string
    {
        if (preg_match('/^namespace\s+([^;]+);/m', $source, $match)) {
            return '\\'.trim($match[1], '\\').'\\'.$class;
        }

        return '\\'.$class;
    }

    private function modelTableName(string $source, string $class): string
    {
        if (preg_match('/protected\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $source, $match)) {
            return $match[1];
        }

        return Str::snake(Str::pluralStudly($class));
    }

    /**
     * @return array<string, array{type: string, description: string}>
     */
    private function modelPropertyDocs(string $source): array
    {
        preg_match_all('/^\s*\*\s*@property(?!-read)\s+(.+)$/m', $source, $matches, PREG_SET_ORDER);

        $properties = [];

        foreach ($matches as $match) {
            $property = trim($match[1]);

            if (! preg_match('/^(.+?)\s+\$([a-zA-Z_][a-zA-Z0-9_]*)(?:\s+(.*))?$/', $property, $parts)) {
                continue;
            }

            $properties[$parts[2]] = [
                'type' => trim($parts[1]),
                // Only the human text after the property name is shown as the column description.
                'description' => trim($parts[3] ?? ''),
            ];
        }

        return $properties;
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
    }
}
