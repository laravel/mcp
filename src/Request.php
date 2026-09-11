<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\JsonSchema\JsonSchema as JsonSchemaFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\InteractsWithData;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Exceptions\InputRequiredException;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Elicitations\ElicitResponse;

/**
 * @implements Arrayable<string, mixed>
 */
class Request implements Arrayable
{
    use Conditionable;
    use InteractsWithData;
    use Macroable;

    /**
     * @var array<int, string>
     */
    protected const PRIMITIVE_TYPES = ['string', 'number', 'integer', 'boolean'];

    protected int $elicitations = 0;

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>|null  $meta
     * @param  array<string, mixed>  $inputResponses
     * @param  array<string, mixed>  $state
     */
    public function __construct(
        protected array $arguments = [],
        protected ?array $meta = null,
        protected ?string $uri = null,
        protected array $inputResponses = [],
        protected array $state = [],
    ) {
        //
    }

    /**
     * @param  array<array-key, string>|array-key|null  $keys
     * @return array<string, mixed>
     */
    public function all(mixed $keys = null): array
    {
        if (is_null($keys)) {
            return $this->data();
        }

        return array_intersect_key($this->data(), array_flip(is_array($keys) ? $keys : func_get_args()));
    }

    protected function data(mixed $key = null, mixed $default = null): mixed
    {
        return data_get($this->arguments, $key, $default);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data($key, $default);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function merge(array $data): static
    {
        $this->arguments = array_merge($this->arguments, $data);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->arguments;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $messages
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $rules, array $messages = [], array $attributes = []): array
    {
        return Validator::validate($this->all(), $rules, $messages, $attributes);
    }

    public function user(?string $guard = null): ?Authenticatable
    {
        $auth = Container::getInstance()->make('auth');

        return call_user_func($auth->userResolver(), $guard);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function meta(): ?array
    {
        return $this->meta;
    }

    public function uri(): ?string
    {
        return $this->uri;
    }

    /**
     * @return array<string, mixed>
     */
    public function inputResponses(): array
    {
        return $this->inputResponses;
    }

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        return $this->state;
    }

    public function shareStateWith(self $request): void
    {
        $this->state = &$request->state;
        $this->elicitations = &$request->elicitations;
    }

    public function remember(string $key, Closure $callback): mixed
    {
        if (! array_key_exists($key, $this->state)) {
            $value = $callback();

            foreach (is_array($value) ? Arr::flatten($value) : [$value] as $leaf) {
                if (! is_null($leaf) && ! is_scalar($leaf)) {
                    throw new InvalidArgumentException("The remembered [{$key}] value must be JSON serializable.");
                }
            }

            $this->state[$key] = $value;
        }

        return $this->state[$key];
    }

    /**
     * @param  Closure(JsonSchema): array<string, mixed>|array<string, mixed>  $schema
     */
    public function ask(string $message, Closure|array $schema, ?string $key = null): ElicitResponse
    {
        $requestedSchema = $schema instanceof Closure
            ? JsonSchemaFactory::object($schema)->toArray()
            : $schema;

        $this->assertRestrictedSchema($requestedSchema);

        $properties = (array) ($requestedSchema['properties'] ?? []);
        $required = (array) ($requestedSchema['required'] ?? []);

        $requestedSchema['type'] ??= 'object';
        $requestedSchema['properties'] = (object) $properties;

        $response = ElicitResponse::from($this->resolveInput([
            'method' => 'elicitation/create',
            'params' => [
                'mode' => 'form',
                'message' => $message,
                'requestedSchema' => $requestedSchema,
            ],
        ], $key));

        if ($response->accepted()) {
            $response->validate(ElicitResponse::rulesFor($properties, $required));
        }

        return $response;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     *
     * @deprecated Sampling is deprecated as of protocol version 2026-07-28.
     */
    public function sample(array $messages, int $maxTokens, array $options = [], ?string $key = null): array
    {
        return $this->resolveInput([
            'method' => 'sampling/createMessage',
            'params' => [...$options, 'messages' => $messages, 'maxTokens' => $maxTokens],
        ], $key);
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @deprecated Roots is deprecated as of protocol version 2026-07-28.
     */
    public function roots(?string $key = null): array
    {
        $roots = $this->resolveInput([
            'method' => 'roots/list',
            'params' => [],
        ], $key)['roots'] ?? [];

        return is_array($roots) ? $roots : [];
    }

    public function canAsk(): bool
    {
        return $this->clientSupports('elicitation.form');
    }

    public function clientSupports(string $capability): bool
    {
        $capabilities = $this->meta[MetaKey::CLIENT_CAPABILITIES->value] ?? [];

        if ($capability === 'elicitation.form' && data_get($capabilities, 'elicitation') === []) {
            return true;
        }

        $declared = data_get($capabilities, $capability);

        return is_bool($declared) ? $declared : is_array($declared);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    protected function assertRestrictedSchema(array $schema): void
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
     * @param  array{method: string, params: array<string, mixed>}  $inputRequest
     * @return array<string, mixed>
     */
    protected function resolveInput(array $inputRequest, ?string $key): array
    {
        $key ??= hash('sha256', json_encode($inputRequest).$this->elicitations++);

        if (array_key_exists($key, $this->inputResponses)) {
            if (! is_array($this->inputResponses[$key])) {
                throw new JsonRpcException("Invalid params: The [inputResponses.{$key}] member must be an object.", -32602);
            }

            return $this->inputResponses[$key];
        }

        throw new InputRequiredException([$key => $inputRequest], $this->inputResponses, $this->state);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function setMeta(?array $meta): void
    {
        $this->meta = $meta;
    }

    /**
     * @param  array<string, mixed>  $inputResponses
     */
    public function setInputResponses(array $inputResponses): void
    {
        $this->inputResponses = $inputResponses;
    }

    public function setUri(?string $uri): void
    {
        $this->uri = $uri;
    }
}
