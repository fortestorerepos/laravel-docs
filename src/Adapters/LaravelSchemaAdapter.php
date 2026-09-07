<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs\Adapters;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;

class LaravelSchemaAdapter
{
    /**
     * @return array{tables: list<array<string, mixed>>, relationships: list<array<string, string>>}
     */
    public function generate(): array
    {
        $connection = $this->connection();
        $schema = $connection->getSchemaBuilder();
        $tables = [];
        $relationships = [];

        $tableNames = $this->tableNames($schema);
        sort($tableNames);

        foreach ($tableNames as $tableName) {
            $foreignKeys = $this->foreignKeys($schema, $tableName);

            $tables[] = [
                'name' => $tableName,
                'columns' => $this->columns($schema, $tableName),
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
                $relationships[] = [
                    'from_table' => $tableName,
                    'from_column' => $foreignKey['column'],
                    'to_table' => $foreignKey['references_table'],
                    'to_column' => $foreignKey['references_column'],
                ];
            }
        }

        return [
            'tables' => $tables,
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
     * @return list<array{name: string, type: string, nullable: bool, default: string, primary: bool}>
     */
    private function columns(Builder $schema, string $tableName): array
    {
        $primaryKeys = $this->primaryKeys($schema, $tableName);

        return array_map(
            fn (array $column): array => [
                'name' => $column['name'],
                'type' => $column['type_name'],
                'nullable' => $column['nullable'],
                'default' => $this->stringValue($column['default']),
                'primary' => in_array($column['name'], $primaryKeys, true),
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
            if ($index['type'] === 'primary') {
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
                'name' => $index['name'],
                'unique' => $index['unique'],
                'columns' => $index['columns'],
                'type' => $index['type'],
            ],
            $schema->getIndexes($tableName),
        );
    }

    /**
     * @return list<array{name: string, column: string, references_table: string, references_column: string}>
     */
    private function foreignKeys(Builder $schema, string $tableName): array
    {
        return array_map(
            fn (array $key): array => [
                'name' => (string) $key['name'],
                'column' => $key['columns'][0] ?? '',
                'references_table' => $key['foreign_table'],
                'references_column' => $key['foreign_columns'][0] ?? '',
            ],
            $schema->getForeignKeys($tableName),
        );
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
