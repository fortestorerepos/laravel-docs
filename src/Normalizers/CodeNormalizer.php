<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs\Normalizers;

use Illuminate\Filesystem\Filesystem;
use SimpleXMLElement;

class CodeNormalizer
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @return array{namespaces: list<array{name: string, classes: list<string>}>, classes: list<array<string, mixed>>}
     */
    public function normalize(string $sourcePath): array
    {
        $xml = $this->firstXml($sourcePath);

        if ($xml === null) {
            return ['namespaces' => [], 'classes' => []];
        }

        $types = [];

        foreach ($xml->xpath('//file') ?: [] as $file) {
            foreach (['class', 'interface', 'trait', 'enum'] as $type) {
                foreach ($file->xpath('./'.$type) ?: [] as $node) {
                    $types[] = $this->normalizeType($node, $type, $this->attribute($file, 'path'));
                }
            }
        }

        if ($types === []) {
            foreach (['class', 'interface', 'trait', 'enum'] as $type) {
                foreach ($xml->xpath('//'.$type) ?: [] as $node) {
                    $types[] = $this->normalizeType($node, $type, '');
                }
            }
        }

        usort($types, fn (array $first, array $second): int => [$first['namespace'], $first['name']] <=> [$second['namespace'], $second['name']]);

        $namespaces = [];

        foreach ($types as $type) {
            $namespace = $type['namespace'];
            $namespaces[$namespace] ??= ['name' => $namespace, 'classes' => []];
            $namespaces[$namespace]['classes'][] = $type['name'];
        }

        return [
            'namespaces' => array_values($namespaces),
            'classes' => $types,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeType(SimpleXMLElement $node, string $type, string $file): array
    {
        $name = $this->value($node, 'name');
        $fqsen = $this->value($node, 'fqsen') ?: $this->value($node, 'full_name');
        $source = $this->sourceMetadata($file);
        $methods = $this->methods($node, $source);

        return [
            'name' => $this->shortName($name, $fqsen),
            'full_name' => $fqsen,
            'namespace' => $this->namespaceFor($node, $name, $fqsen),
            'type' => $type,
            'summary' => $this->summary($node),
            'description' => $this->longDescription($node),
            'parent' => $this->attribute($node, 'extends') ?: $this->textFromFirst($node, ['extends', 'parent']),
            'file' => $file,
            'line' => $this->integerAttribute($node, 'line'),
            'final' => $this->booleanAttribute($node, 'final'),
            'abstract' => $this->booleanAttribute($node, 'abstract'),
            'interfaces' => $this->valuesFrom($node, ['implements', 'interface']),
            'backing_type' => $type === 'enum' ? $this->enumBackingType($node, $source) : '',
            'cases' => $type === 'enum' ? $this->cases($node, $source) : [],
            'methods' => $methods,
            'properties' => $this->properties($node, $methods, $source),
        ];
    }

    /**
     * @param  array{imports: array<string, string>, method_returns: array<string, string>, property_types: array<string, string>, enum_backing_type: string, enum_cases: array<string, array{name: string, value: string, summary: string, line: int}>}  $source
     * @return list<array<string, mixed>>
     */
    private function methods(SimpleXMLElement $node, array $source): array
    {
        $methods = [];

        foreach ($node->xpath('.//method') ?: [] as $method) {
            $name = $this->value($method, 'name');
            $line = $this->integerAttribute($method, 'line');

            $methods[] = [
                'name' => $name,
                'full_name' => $this->value($method, 'full_name'),
                'summary' => $this->summary($method),
                'description' => $this->longDescription($method),
                'visibility' => $this->attribute($method, 'visibility') ?: 'public',
                'line' => $line,
                'final' => $this->booleanAttribute($method, 'final'),
                'abstract' => $this->booleanAttribute($method, 'abstract'),
                'static' => $this->booleanAttribute($method, 'static'),
                'return_by_reference' => $this->booleanAttribute($method, 'returnByReference'),
                'inherited_from' => $this->textFromDirectChild($method, 'inherited_from'),
                'parameters' => $this->parameters($method),
                'return_type' => $this->returnType($method) ?: $this->sourceMethodReturnType($source, $name, $line),
                'return_description' => $this->tagDescription($method, 'return'),
            ];
        }

        return $methods;
    }

    /**
     * @return list<array{name: string, type: string, default: string}>
     */
    private function parameters(SimpleXMLElement $method): array
    {
        $parameters = [];

        foreach ($method->xpath('.//argument|.//parameter|.//param') ?: [] as $parameter) {
            $name = ltrim($this->value($parameter, 'name') ?: $this->attribute($parameter, 'variable'), '$');

            $parameters[] = [
                'name' => $name,
                'type' => $this->value($parameter, 'type'),
                'default' => $this->value($parameter, 'default'),
                'description' => $this->parameterDescription($method, $name),
                'line' => $this->integerAttribute($parameter, 'line'),
                'by_reference' => $this->booleanAttribute($parameter, 'by_reference'),
            ];
        }

        return $parameters;
    }

    /**
     * @param  list<array<string, mixed>>  $methods
     * @param  array{imports: array<string, string>, method_returns: array<string, string>, property_types: array<string, string>, enum_backing_type: string, enum_cases: array<string, array{name: string, value: string, summary: string, line: int}>}  $source
     * @return list<array{name: string, type: string, summary: string}>
     */
    private function properties(SimpleXMLElement $node, array $methods, array $source): array
    {
        $properties = [];

        foreach ($node->xpath('.//property') ?: [] as $property) {
            $name = $this->value($property, 'name');

            $properties[] = [
                'name' => $name,
                'full_name' => $this->value($property, 'full_name'),
                'type' => $this->value($property, 'type')
                    ?: $this->sourcePropertyType($source, $name, $this->integerAttribute($property, 'line'))
                    ?: $this->promotedPropertyType($methods, $name, $this->integerAttribute($property, 'line')),
                'summary' => $this->summary($property),
                'description' => $this->longDescription($property),
                'visibility' => $this->attribute($property, 'visibility') ?: 'public',
                'line' => $this->integerAttribute($property, 'line'),
                'default' => $this->value($property, 'default'),
                'static' => $this->booleanAttribute($property, 'static'),
                'read_only' => $this->booleanAttribute($property, 'read_only') || $this->booleanAttribute($property, 'readonly'),
                'inherited_from' => $this->textFromDirectChild($property, 'inherited_from'),
            ];
        }

        return $properties;
    }

    /**
     * @param  list<array<string, mixed>>  $methods
     */
    private function promotedPropertyType(array $methods, string $name, int $line): string
    {
        foreach ($methods as $method) {
            if (($method['name'] ?? '') !== '__construct' || (int) ($method['line'] ?? 0) !== $line) {
                continue;
            }

            foreach ($method['parameters'] ?? [] as $parameter) {
                if (($parameter['name'] ?? '') === $name) {
                    return (string) ($parameter['type'] ?? '');
                }
            }
        }

        return '';
    }

    /**
     * @param  array{enum_backing_type: string}  $source
     */
    private function enumBackingType(SimpleXMLElement $node, array $source): string
    {
        return $this->attribute($node, 'backingType')
            ?: $this->attribute($node, 'backing_type')
            ?: $this->textFromDirectChild($node, 'backingType')
            ?: $this->textFromDirectChild($node, 'backing_type')
            ?: $source['enum_backing_type'];
    }

    /**
     * @param  array{enum_cases: array<string, array{name: string, value: string, summary: string, line: int}>}  $source
     * @return list<array{name: string, full_name: string, value: string, summary: string, description: string, line: int}>
     */
    private function cases(SimpleXMLElement $node, array $source): array
    {
        $cases = [];

        foreach ($node->xpath('./case|./constant') ?: [] as $case) {
            $name = $this->value($case, 'name');
            $sourceCase = $source['enum_cases'][$name] ?? ['value' => '', 'summary' => '', 'line' => 0];

            $cases[] = [
                'name' => $name,
                'full_name' => $this->value($case, 'full_name'),
                'value' => html_entity_decode($this->value($case, 'value') ?: $sourceCase['value'], ENT_QUOTES | ENT_XML1),
                'summary' => $this->summary($case) ?: $sourceCase['summary'],
                'description' => $this->longDescription($case),
                'line' => $this->integerAttribute($case, 'line') ?: $sourceCase['line'],
            ];
        }

        if ($cases !== []) {
            return $cases;
        }

        return array_map(fn (array $case): array => [
            'name' => $case['name'],
            'full_name' => '',
            'value' => $case['value'],
            'summary' => $case['summary'],
            'description' => '',
            'line' => $case['line'],
        ], array_values($source['enum_cases']));
    }

    /**
     * @return array{imports: array<string, string>, method_returns: array<string, string>, property_types: array<string, string>, enum_backing_type: string, enum_cases: array<string, array{name: string, value: string, summary: string, line: int}>}
     */
    private function sourceMetadata(string $file): array
    {
        $path = $this->sourceFile($file);

        if ($path === null) {
            return ['imports' => [], 'method_returns' => [], 'property_types' => [], 'enum_backing_type' => '', 'enum_cases' => []];
        }

        $source = $this->files->get($path);
        $imports = $this->imports($source);

        return [
            'imports' => $imports,
            'method_returns' => $this->sourceMethodReturns($source, $imports),
            'property_types' => $this->sourcePropertyTypes($source, $imports),
            'enum_backing_type' => $this->sourceEnumBackingType($source, $imports),
            'enum_cases' => $this->sourceEnumCases($source),
        ];
    }

    private function sourceFile(string $file): ?string
    {
        if ($file === '') {
            return null;
        }

        foreach (config('laravel-docs.code.paths', []) as $path) {
            $candidate = rtrim((string) $path, '/\\').DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);

            if ($this->files->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function imports(string $source): array
    {
        preg_match_all('/^use\s+(?!function\s|const\s)([^;]+);/mi', $source, $matches);

        $imports = [];

        foreach ($matches[1] as $use) {
            $class = trim($use);
            $parts = preg_split('/\s+as\s+/i', $class) ?: [$class];
            $alias = isset($parts[1]) ? trim($parts[1]) : $this->shortName(trim($parts[0]), trim($parts[0]));

            $imports[$alias] = '\\'.trim($parts[0], '\\');
        }

        return $imports;
    }

    /**
     * @param  array<string, string>  $imports
     * @return array<string, string>
     */
    private function sourceMethodReturns(string $source, array $imports): array
    {
        preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)\s*:\s*([^\s{;]+)/m', $source, $matches, PREG_SET_ORDER);

        $returns = [];

        foreach ($matches as $match) {
            $returns[$match[1]] = $this->resolveImportedType($match[2], $imports);
        }

        return $returns;
    }

    /**
     * @param  array<string, string>  $imports
     * @return array<string, string>
     */
    private function sourcePropertyTypes(string $source, array $imports): array
    {
        preg_match_all('/(?:public|protected|private)\s+(?:readonly\s+)?(?:static\s+)?([?\\\\a-zA-Z_][\\\\a-zA-Z0-9_|&?]*)\s+\$([a-zA-Z_][a-zA-Z0-9_]*)/m', $source, $matches, PREG_SET_ORDER);

        $types = [];

        foreach ($matches as $match) {
            $types[$match[2]] = $this->resolveImportedType($match[1], $imports);
        }

        return $types;
    }

    /**
     * @param  array<string, string>  $imports
     */
    private function sourceEnumBackingType(string $source, array $imports): string
    {
        if (! preg_match('/enum\s+[a-zA-Z_][a-zA-Z0-9_]*\s*:\s*([^\s{]+)/m', $source, $match)) {
            return '';
        }

        return $this->resolveImportedType($match[1], $imports);
    }

    /**
     * @return array<string, array{name: string, value: string, summary: string, line: int}>
     */
    private function sourceEnumCases(string $source): array
    {
        $cases = [];
        $pendingComment = '';
        $lines = preg_split('/\R/', $source) ?: [];

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*\/\/\s*(.+)\s*$/', $line, $comment)) {
                $pendingComment = trim($comment[1]);

                continue;
            }

            if (preg_match('/^\s*case\s+([a-zA-Z_][a-zA-Z0-9_]*)(?:\s*=\s*([^;]+))?\s*;/', $line, $case)) {
                $name = $case[1];
                $cases[$name] = [
                    'name' => $name,
                    'value' => isset($case[2]) ? trim($case[2]) : '',
                    'summary' => $pendingComment,
                    'line' => $index + 1,
                ];
                $pendingComment = '';

                continue;
            }

            if (trim($line) !== '') {
                $pendingComment = '';
            }
        }

        return $cases;
    }

    /**
     * @param  array{method_returns: array<string, string>}  $source
     */
    private function sourceMethodReturnType(array $source, string $name, int $line): string
    {
        unset($line);

        return $source['method_returns'][$name] ?? '';
    }

    /**
     * @param  array{property_types: array<string, string>}  $source
     */
    private function sourcePropertyType(array $source, string $name, int $line): string
    {
        unset($line);

        return $source['property_types'][$name] ?? '';
    }

    /**
     * @param  array<string, string>  $imports
     */
    private function resolveImportedType(string $type, array $imports): string
    {
        if ($type === '') {
            return '';
        }

        return preg_replace_callback('/\\\\?[a-zA-Z_][\\\\a-zA-Z0-9_]*/', function (array $match) use ($imports): string {
            $value = $match[0];
            $trimmed = ltrim($value, '\\');
            $builtIns = ['array', 'bool', 'callable', 'false', 'float', 'int', 'iterable', 'mixed', 'never', 'null', 'object', 'self', 'static', 'string', 'true', 'void'];

            if (in_array(strtolower($trimmed), $builtIns, true) || str_starts_with($value, '\\')) {
                return $value;
            }

            return $imports[$trimmed] ?? $value;
        }, $type) ?? $type;
    }

    private function firstXml(string $sourcePath): ?SimpleXMLElement
    {
        if (! $this->files->isDirectory($sourcePath)) {
            return null;
        }

        foreach ($this->files->allFiles($sourcePath) as $file) {
            if ($file->getExtension() !== 'xml') {
                continue;
            }

            $xml = simplexml_load_string($this->files->get($file->getPathname()));

            if ($xml instanceof SimpleXMLElement) {
                return $xml;
            }
        }

        return null;
    }

    private function attribute(SimpleXMLElement $node, string $attribute): string
    {
        return trim((string) ($node->attributes()[$attribute] ?? ''));
    }

    private function integerAttribute(SimpleXMLElement $node, string $attribute): int
    {
        return (int) $this->attribute($node, $attribute);
    }

    private function booleanAttribute(SimpleXMLElement $node, string $attribute): bool
    {
        return in_array(strtolower($this->attribute($node, $attribute)), ['1', 'true', 'yes'], true);
    }

    private function value(SimpleXMLElement $node, string $name): string
    {
        return $this->attribute($node, $name) ?: trim((string) ($node->{$name} ?? ''));
    }

    private function returnType(SimpleXMLElement $method): string
    {
        $return = $this->attribute($method, 'return') ?: $this->textFromDirectChild($method, 'return');

        if ($return !== '') {
            return $return;
        }

        foreach ($method->xpath('./docblock/tag[@name="return"]') ?: [] as $tag) {
            $type = $this->attribute($tag, 'type');

            if ($type !== '') {
                return $type;
            }
        }

        return '';
    }

    private function summary(SimpleXMLElement $node): string
    {
        return trim((string) ($node->docblock->description ?? $node->description ?? $node->summary ?? ''));
    }

    private function longDescription(SimpleXMLElement $node): string
    {
        return trim((string) ($node->docblock->{'long-description'} ?? $node->{'long-description'} ?? ''));
    }

    private function parameterDescription(SimpleXMLElement $method, string $name): string
    {
        foreach ($method->xpath('./docblock/tag[@name="param"]') ?: [] as $tag) {
            if (ltrim($this->attribute($tag, 'variable'), '$') === $name) {
                return $this->attribute($tag, 'description');
            }
        }

        return '';
    }

    private function tagDescription(SimpleXMLElement $node, string $name): string
    {
        foreach ($node->xpath('./docblock/tag[@name="'.$name.'"]') ?: [] as $tag) {
            return $this->attribute($tag, 'description');
        }

        return '';
    }

    /**
     * @param  list<string>  $names
     */
    private function textFromFirst(SimpleXMLElement $node, array $names): string
    {
        foreach ($names as $name) {
            $result = $node->xpath('.//'.$name);

            if ($result !== false && isset($result[0])) {
                return trim((string) $result[0]);
            }
        }

        return '';
    }

    private function textFromDirectChild(SimpleXMLElement $node, string $name): string
    {
        return trim((string) ($node->{$name} ?? ''));
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
                $value = $this->attribute($item, 'name') ?: trim((string) $item);

                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return array_values(array_unique($values));
    }

    private function shortName(string $name, string $fqsen): string
    {
        $value = $name !== '' ? $name : trim($fqsen, '\\');
        $parts = explode('\\', $value);

        return (string) end($parts);
    }

    private function namespaceFor(SimpleXMLElement $node, string $name, string $fqsen): string
    {
        $namespace = $this->attribute($node, 'namespace') ?: trim((string) ($node->namespace ?? ''));

        if ($namespace !== '') {
            return trim($namespace, '\\');
        }

        $value = trim($fqsen !== '' ? $fqsen : $name, '\\');
        $parts = explode('\\', $value);
        array_pop($parts);

        return implode('\\', $parts);
    }
}
