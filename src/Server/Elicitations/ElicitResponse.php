<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitations;

use ArrayAccess;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Laravel\Mcp\Enums\ElicitationAction;
use Laravel\Mcp\Exceptions\JsonRpcException;
use LogicException;

/**
 * @implements ArrayAccess<string, mixed>
 */
class ElicitResponse implements ArrayAccess
{
    /**
     * @var array<int, string>
     */
    protected const PRIMITIVE_TYPES = ['string', 'number', 'integer', 'boolean'];

    /**
     * @param  array<string, mixed>  $content
     */
    public function __construct(
        protected ?ElicitationAction $action = null,
        protected array $content = [],
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function from(array $response): static
    {
        $action = Arr::get($response, 'action');
        $resolved = ElicitationAction::tryFrom(is_string($action) ? $action : '');

        if (is_null($resolved)) {
            throw new JsonRpcException(sprintf(
                'Invalid params: The elicitation response action [%s] must be one of [accept, decline, cancel].',
                is_string($action) ? $action : get_debug_type($action),
            ), -32602);
        }

        $content = Arr::get($response, 'content');

        if ($resolved === ElicitationAction::Accept && ! is_null($content) && ! is_array($content)) {
            throw new JsonRpcException('Invalid params: The elicitation response content must be an object.', -32602);
        }

        return new static($resolved, is_array($content) ? $content : []);
    }

    public function action(): ?ElicitationAction
    {
        return $this->action;
    }

    public function accepted(): bool
    {
        return $this->action === ElicitationAction::Accept;
    }

    public function declined(): bool
    {
        return $this->action === ElicitationAction::Decline;
    }

    public function cancelled(): bool
    {
        return $this->action === ElicitationAction::Cancel;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (! $this->accepted()) {
            return func_num_args() > 1 ? $default : $this->reject();
        }

        return $this->content[$key] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public static function assertFormSchema(array $schema): void
    {
        if (($schema['type'] ?? 'object') !== 'object') {
            throw new InvalidArgumentException('Form elicitation schemas must declare a root type of [object].');
        }

        $properties = (array) ($schema['properties'] ?? []);

        foreach ($properties as $name => $property) {
            $type = is_array($property) ? $property['type'] ?? null : null;

            if ($type === 'object') {
                throw new InvalidArgumentException("The [{$name}] property must be a primitive. Form elicitation schemas may not nest objects.");
            }

            if ($type === 'array') {
                $items = is_array($property['items'] ?? null) ? $property['items'] : [];
                $allowed = $items['enum'] ?? $items['anyOf'] ?? null;

                if (! is_array($allowed) || $allowed === []) {
                    throw new InvalidArgumentException("The [{$name}] property must be an enum array. Form elicitation schemas only allow arrays of enum values.");
                }

                continue;
            }

            if (! in_array($type, static::PRIMITIVE_TYPES, true)) {
                throw new InvalidArgumentException("The [{$name}] property must declare one of the [".implode(', ', static::PRIMITIVE_TYPES).'] types.');
            }
        }

        foreach ((array) ($schema['required'] ?? []) as $name) {
            if (! is_string($name) || ! array_key_exists($name, $properties)) {
                throw new InvalidArgumentException('The [required] member may only list declared properties.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  array<array-key, mixed>  $required
     * @return array<string, array<int, mixed>>
     */
    public static function rulesFor(array $properties, array $required): array
    {
        $types = [
            'string' => 'string',
            'integer' => 'integer',
            'number' => 'numeric',
            'boolean' => 'boolean',
            'array' => 'array',
        ];

        return Arr::mapWithKeys($properties, function (mixed $property, string $name) use ($required, $types): array {
            $property = is_array($property) ? $property : [];
            $type = $property['type'] ?? null;
            $allowed = static::allowedValues($property);
            $min = $property['minLength'] ?? $property['minimum'] ?? $property['minItems'] ?? null;
            $max = $property['maxLength'] ?? $property['maximum'] ?? $property['maxItems'] ?? null;

            $rules = [$name => array_values(array_filter([
                in_array($name, $required, true) ? 'present' : 'sometimes',
                is_int($property['minLength'] ?? null) && $property['minLength'] > 0 ? 'required' : null,
                is_string($type) ? $types[$type] ?? null : null,
                is_string($type) ? static::jsonType($type) : null,
                is_null($allowed) ? null : Rule::in($allowed),
                is_scalar($min) ? "min:{$min}" : null,
                is_scalar($max) ? "max:{$max}" : null,
                match ($property['format'] ?? null) {
                    'email' => 'email',
                    'uri' => 'url',
                    'date' => 'date_format:Y-m-d',
                    'date-time' => 'date',
                    default => null,
                },
            ]))];

            $items = static::allowedValues(is_array($property['items'] ?? null) ? $property['items'] : []);

            if (! is_null($items)) {
                $rules["{$name}.*"] = [Rule::in($items)];
            }

            return $rules;
        });
    }

    protected static function jsonType(string $type): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($type): void {
            $valid = match ($type) {
                'string' => is_string($value),
                'integer' => is_int($value),
                'number' => is_int($value) || is_float($value),
                'boolean' => is_bool($value),
                'array' => is_array($value) && array_is_list($value),
                default => true,
            };

            if (! $valid) {
                $fail("The {$attribute} field must be of type {$type}.");
            }
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, mixed>|null
     */
    protected static function allowedValues(array $schema): ?array
    {
        if (is_array($schema['enum'] ?? null) && $schema['enum'] !== []) {
            return array_values($schema['enum']);
        }

        foreach (['oneOf', 'anyOf'] as $key) {
            if (! is_array($schema[$key] ?? null)) {
                continue;
            }

            $values = array_values(array_filter(
                Arr::pluck($schema[$key], 'const'),
                fn (mixed $const): bool => ! is_null($const),
            ));

            if ($values !== []) {
                return $values;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public function validate(array $rules): array
    {
        return $this->accepted() ? Validator::validate($this->content, $rules) : $this->reject();
    }

    protected function reject(): never
    {
        throw new LogicException(sprintf(
            'The elicitation was not accepted. Check accepted() before reading the response [action: %s].',
            $this->action instanceof ElicitationAction ? $this->action->value : 'missing',
        ));
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->accepted() && isset($this->content[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    /**
     * @return array<string, mixed>
     */
    public function content(): array
    {
        return $this->accepted() ? $this->content : [];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Elicitation responses are immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Elicitation responses are immutable.');
    }
}
