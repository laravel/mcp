<?php

declare(strict_types=1);

namespace Laravel\Mcp\Support;

use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Transport\HeaderValue;

class ToolHeaders
{
    public const ANNOTATION = 'x-mcp-header';

    public const PREFIX = 'Mcp-Param-';

    protected const SAFE_INTEGER = 9007199254740991;

    /**
     * @param  array<string, mixed>  $inputSchema
     */
    public static function invalid(array $inputSchema): ?string
    {
        $seen = [];

        foreach (self::annotated($inputSchema) as $path => $name) {
            if (! is_string($name) || preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D', $name) !== 1) {
                return 'the ['.self::ANNOTATION."] value on [{$path}] is not a valid header name token";
            }

            $lowered = strtolower($name);

            if (isset($seen[$lowered])) {
                return 'the ['.self::ANNOTATION."] value [{$name}] is used more than once";
            }

            $seen[$lowered] = true;
        }

        foreach (self::annotatedSchemas($inputSchema) as $path => $schema) {
            $type = $schema['type'] ?? null;

            if (! in_array($type, ['string', 'integer', 'boolean'], true)) {
                return 'the ['.self::ANNOTATION."] annotation on [{$path}] must sit on a string, integer, or boolean";
            }
        }

        if (self::unreachable($inputSchema)) {
            return 'an ['.self::ANNOTATION.'] annotation sits outside the statically reachable properties';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $inputSchema
     * @param  array<string, mixed>  $arguments
     * @return array<string, string>
     */
    public static function extract(array $inputSchema, array $arguments): array
    {
        $headers = [];

        foreach (self::annotated($inputSchema) as $path => $name) {
            $value = self::valueAt($arguments, explode('.', $path));
            if ($value === null) {
                continue;
            }

            if (! self::encodable($value)) {
                throw new ClientException(
                    "The [{$path}] argument is annotated with [".self::ANNOTATION.'] but its value cannot be mirrored into a header.',
                );
            }

            $headers[self::PREFIX.$name] = (string) new HeaderValue(self::stringify($value));
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $inputSchema
     * @return array<string, mixed>
     */
    protected static function annotated(array $inputSchema, string $prefix = ''): array
    {
        $names = [];

        foreach (self::properties($inputSchema) as $key => $schema) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (array_key_exists(self::ANNOTATION, $schema)) {
                $names[$path] = $schema[self::ANNOTATION];
            }

            $names = [...$names, ...self::annotated($schema, $path)];
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $inputSchema
     * @return array<string, array<string, mixed>>
     */
    protected static function annotatedSchemas(array $inputSchema, string $prefix = ''): array
    {
        $schemas = [];

        foreach (self::properties($inputSchema) as $key => $schema) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (array_key_exists(self::ANNOTATION, $schema)) {
                $schemas[$path] = $schema;
            }

            $schemas = [...$schemas, ...self::annotatedSchemas($schema, $path)];
        }

        return $schemas;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<array-key, array<string, mixed>>
     */
    protected static function properties(array $schema): array
    {
        $properties = $schema['properties'] ?? null;

        if (! is_array($properties)) {
            return [];
        }

        return array_filter($properties, is_array(...));
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    protected static function unreachable(array $schema): bool
    {
        foreach ($schema as $key => $value) {
            if ($key === self::ANNOTATION) {
                return true;
            }

            if (! is_array($value)) {
                continue;
            }

            if ($key !== 'properties') {
                if (self::containsAnnotation($value)) {
                    return true;
                }

                continue;
            }

            foreach ($value as $child) {
                if (! is_array($child)) {
                    continue;
                }

                unset($child[self::ANNOTATION]);

                if (self::unreachable($child)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<array-key, mixed>  $schema
     */
    protected static function containsAnnotation(array $schema): bool
    {
        foreach ($schema as $key => $value) {
            if ($key === self::ANNOTATION) {
                return true;
            }

            if (is_array($value) && self::containsAnnotation($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, string>  $path
     */
    protected static function valueAt(array $arguments, array $path): mixed
    {
        $value = $arguments;

        foreach ($path as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    protected static function encodable(mixed $value): bool
    {
        if (is_bool($value) || is_string($value)) {
            return true;
        }

        return is_int($value) && abs($value) <= self::SAFE_INTEGER;
    }

    protected static function stringify(string|int|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
