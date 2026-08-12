<?php

declare(strict_types=1);

namespace Laravel\Mcp\Support;

use Countable;
use Illuminate\Support\Collection;
use Laravel\Mcp\Exceptions\MirroredParameterException;
use Laravel\Mcp\Transport\HeaderValue;

class MirroredParameters implements Countable
{
    public const ANNOTATION = 'x-mcp-header';

    public const PREFIX = 'Mcp-Param-';

    protected const TOKEN = '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D';

    /**
     * @param  Collection<int, MirroredParameter>  $parameters
     */
    protected function __construct(protected Collection $parameters)
    {
        //
    }

    public static function none(): self
    {
        return new self(collect());
    }

    /**
     * @param  array<string, mixed>  $inputSchema
     *
     * @throws MirroredParameterException
     */
    public static function fromSchema(array $inputSchema): self
    {
        $parameters = collect(self::parse($inputSchema, SchemaPath::root()));

        $duplicates = $parameters->duplicates(
            fn (MirroredParameter $parameter): string => strtolower($parameter->name),
        );

        if ($duplicates->isNotEmpty()) {
            throw new MirroredParameterException(
                'the ['.self::ANNOTATION.'] value ['.$duplicates->implode(', ').'] is used more than once',
            );
        }

        return new self($parameters);
    }

    /**
     * @param  array<string, string>  $declarations
     * @param  array<string, mixed>  $inputSchema
     * @return array<string, mixed>
     */
    public static function annotate(array $declarations, array $inputSchema): array
    {
        foreach ($declarations as $pointer => $name) {
            $inputSchema = SchemaPath::fromPointer($pointer)->annotate($inputSchema, self::ANNOTATION, $name);
        }

        return $inputSchema;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, string>
     *
     * @throws MirroredParameterException
     */
    public function headers(array $arguments): array
    {
        $headers = [];

        foreach ($this->parameters as $parameter) {
            $value = $parameter->value($arguments);

            if ($value instanceof HeaderValue) {
                $headers[$parameter->header()] = (string) $value;
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $arguments
     */
    public function mismatch(array $headers, array $arguments, bool $required): ?string
    {
        $received = collect($headers)->mapWithKeys(
            fn (string $value, string $name): array => [strtolower($name) => $value],
        );

        foreach ($this->parameters as $parameter) {
            $header = $received->get(strtolower($parameter->header()));
            $expected = $parameter->stringify($parameter->raw($arguments));

            if ($header === null) {
                if ($expected !== null && $required) {
                    return "the [{$parameter->header()}] header is required for the [{$parameter->path}] argument";
                }

                continue;
            }

            $value = HeaderValue::fromHeader($header)->value;

            if ($expected === null) {
                return "the [{$parameter->header()}] header was sent for the missing [{$parameter->path}] argument";
            }

            if (! $this->matches($parameter, $value, $expected)) {
                return "the [{$parameter->header()}] header value [{$value}] does not match the [{$parameter->path}] argument [{$expected}]";
            }
        }

        return null;
    }

    /**
     * @return Collection<int, MirroredParameter>
     */
    public function all(): Collection
    {
        return $this->parameters;
    }

    public function count(): int
    {
        return count($this->parameters);
    }

    protected function matches(MirroredParameter $parameter, string $value, string $expected): bool
    {
        if ($parameter->type === MirroredParameterType::Integer && is_numeric($value)) {
            return (float) $value === (float) $expected;
        }

        return $value === $expected;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<MirroredParameter>
     *
     * @throws MirroredParameterException
     */
    protected static function parse(array $schema, SchemaPath $path): array
    {
        $parameters = [];

        foreach ($schema as $key => $value) {
            if ($key === self::ANNOTATION) {
                throw new MirroredParameterException(
                    'an ['.self::ANNOTATION.'] annotation sits outside the statically reachable properties',
                );
            }

            if (! is_array($value)) {
                continue;
            }

            if ($key !== 'properties') {
                if (self::containsAnnotation($value)) {
                    throw new MirroredParameterException(
                        'an ['.self::ANNOTATION.'] annotation sits outside the statically reachable properties',
                    );
                }

                continue;
            }

            foreach ($value as $property => $child) {
                if (! is_array($child)) {
                    continue;
                }

                $childPath = $path->child((string) $property);

                if (array_key_exists(self::ANNOTATION, $child)) {
                    $parameters[] = self::parameter($child, $childPath);

                    unset($child[self::ANNOTATION]);
                }

                $parameters = [...$parameters, ...self::parse($child, $childPath)];
            }
        }

        return $parameters;
    }

    /**
     * @param  array<string, mixed>  $schema
     *
     * @throws MirroredParameterException
     */
    protected static function parameter(array $schema, SchemaPath $path): MirroredParameter
    {
        $name = $schema[self::ANNOTATION];

        if (! is_string($name) || preg_match(self::TOKEN, $name) !== 1) {
            throw new MirroredParameterException(
                'the ['.self::ANNOTATION."] value on [{$path}] is not a valid header name token",
            );
        }

        $type = MirroredParameterType::fromSchema($schema['type'] ?? null);

        if (! $type instanceof MirroredParameterType) {
            throw new MirroredParameterException(
                'the ['.self::ANNOTATION."] annotation on [{$path}] must sit on a string, integer, or boolean",
            );
        }

        return new MirroredParameter($path, $name, $type);
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
}
