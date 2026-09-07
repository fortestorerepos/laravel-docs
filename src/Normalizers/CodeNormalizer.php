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
            'methods' => $this->methods($node),
            'properties' => $this->properties($node),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function methods(SimpleXMLElement $node): array
    {
        $methods = [];

        foreach ($node->xpath('.//method') ?: [] as $method) {
            $methods[] = [
                'name' => $this->value($method, 'name'),
                'full_name' => $this->value($method, 'full_name'),
                'summary' => $this->summary($method),
                'description' => $this->longDescription($method),
                'visibility' => $this->attribute($method, 'visibility') ?: 'public',
                'line' => $this->integerAttribute($method, 'line'),
                'final' => $this->booleanAttribute($method, 'final'),
                'abstract' => $this->booleanAttribute($method, 'abstract'),
                'static' => $this->booleanAttribute($method, 'static'),
                'return_by_reference' => $this->booleanAttribute($method, 'returnByReference'),
                'inherited_from' => $this->textFromDirectChild($method, 'inherited_from'),
                'parameters' => $this->parameters($method),
                'return_type' => $this->returnType($method),
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
     * @return list<array{name: string, type: string, summary: string}>
     */
    private function properties(SimpleXMLElement $node): array
    {
        $properties = [];

        foreach ($node->xpath('.//property') ?: [] as $property) {
            $properties[] = [
                'name' => $this->value($property, 'name'),
                'full_name' => $this->value($property, 'full_name'),
                'type' => $this->value($property, 'type'),
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
