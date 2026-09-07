<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs\Normalizers;

use Illuminate\Filesystem\Filesystem;
use SimpleXMLElement;

class DatabaseNormalizer
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @return array{tables: list<array<string, mixed>>, relationships: list<array<string, string>>}
     */
    public function normalize(string $sourcePath): array
    {
        $xmlFiles = $this->xmlFiles($sourcePath);

        if ($xmlFiles === []) {
            return ['tables' => [], 'relationships' => []];
        }

        $tables = [];
        $relationships = [];

        foreach ($xmlFiles as $xml) {
            foreach ($xml->xpath('//table') ?: [] as $table) {
                $normalized = $this->table($table);

                if ($normalized['name'] === '') {
                    continue;
                }

                $tables[$normalized['name']] = $normalized;
                $relationships = array_merge($relationships, $normalized['relationships']);
            }
        }

        ksort($tables);

        return [
            'tables' => array_values($tables),
            'relationships' => $relationships,
        ];
    }

    /**
     * @return array{name: string, columns: list<array{name: string, type: string, nullable: bool, default: string, primary: bool}>, primary_keys: list<string>, foreign_keys: list<array{name: string, column: string, references_table: string, references_column: string}>, indexes: list<array{name: string, unique: bool, columns: list<string>}>, relationships: list<array<string, string>>}
     */
    private function table(SimpleXMLElement $table): array
    {
        $foreignKeys = $this->foreignKeys($table);

        return [
            'name' => $this->attribute($table, 'name'),
            'columns' => $this->columns($table),
            'primary_keys' => $this->valuesFrom($table, ['primaryKey', 'primary-key', 'primary_key']),
            'foreign_keys' => $foreignKeys,
            'indexes' => $this->indexes($table),
            'relationships' => array_map(
                fn (array $key): array => [
                    'from_table' => $this->attribute($table, 'name'),
                    'from_column' => $key['column'],
                    'to_table' => $key['references_table'],
                    'to_column' => $key['references_column'],
                ],
                $foreignKeys,
            ),
        ];
    }

    /**
     * @return list<array{name: string, type: string, nullable: bool, default: string, primary: bool}>
     */
    private function columns(SimpleXMLElement $table): array
    {
        $columns = [];

        foreach ($table->xpath('.//column') ?: [] as $column) {
            $columns[] = [
                'name' => $this->attribute($column, 'name'),
                'type' => $this->attribute($column, 'type') ?: $this->attribute($column, 'dataType'),
                'nullable' => $this->truthy($this->attribute($column, 'nullable')),
                'default' => $this->attribute($column, 'default'),
                'primary' => $this->truthy($this->attribute($column, 'primaryKey') ?: $this->attribute($column, 'primary')),
            ];
        }

        return $columns;
    }

    /**
     * @return list<array{name: string, column: string, references_table: string, references_column: string}>
     */
    private function foreignKeys(SimpleXMLElement $table): array
    {
        $keys = [];

        foreach ($table->xpath('.//foreignKey|.//foreign-key|.//foreign_key') ?: [] as $key) {
            $keys[] = [
                'name' => $this->attribute($key, 'name'),
                'column' => $this->attribute($key, 'column') ?: $this->attribute($key, 'fromColumn'),
                'references_table' => $this->attribute($key, 'referencesTable') ?: $this->attribute($key, 'toTable'),
                'references_column' => $this->attribute($key, 'referencesColumn') ?: $this->attribute($key, 'toColumn'),
            ];
        }

        return $keys;
    }

    /**
     * @return list<array{name: string, unique: bool, columns: list<string>}>
     */
    private function indexes(SimpleXMLElement $table): array
    {
        $indexes = [];

        foreach ($table->xpath('.//index') ?: [] as $index) {
            $indexes[] = [
                'name' => $this->attribute($index, 'name'),
                'unique' => $this->truthy($this->attribute($index, 'unique')),
                'columns' => $this->valuesFrom($index, ['column']),
            ];
        }

        return $indexes;
    }

    /**
     * @return list<SimpleXMLElement>
     */
    private function xmlFiles(string $sourcePath): array
    {
        if (! $this->files->isDirectory($sourcePath)) {
            return [];
        }

        $xmlFiles = [];

        foreach ($this->files->allFiles($sourcePath) as $file) {
            if ($file->getExtension() !== 'xml') {
                continue;
            }

            $xml = simplexml_load_string($this->files->get($file->getPathname()));

            if ($xml instanceof SimpleXMLElement) {
                $xmlFiles[] = $xml;
            }
        }

        return $xmlFiles;
    }

    private function attribute(SimpleXMLElement $node, string $attribute): string
    {
        return trim((string) ($node->attributes()[$attribute] ?? ''));
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function valuesFrom(SimpleXMLElement $node, array $names): array
    {
        $values = [];

        foreach ($names as $name) {
            foreach ($node->xpath('.//'.$name) ?: [] as $item) {
                $value = $this->attribute($item, 'name') ?: $this->attribute($item, 'column') ?: trim((string) $item);

                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return array_values(array_unique($values));
    }

    private function truthy(string $value): bool
    {
        return in_array(strtolower($value), ['1', 'true', 'yes', 'y'], true);
    }
}
