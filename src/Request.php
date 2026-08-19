<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\JsonSchema\JsonSchema as JsonSchemaFactory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\InteractsWithData;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Exceptions\ElicitationNotSupportedException;
use Laravel\Mcp\Exceptions\InputRequiredException;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Exceptions\RootsNotSupportedException;
use Laravel\Mcp\Exceptions\SamplingNotSupportedException;
use Laravel\Mcp\Server\Elicitations\ElicitResponse;

/**
 * @implements Arrayable<string, mixed>
 */
class Request implements Arrayable
{
    use Conditionable;
    use InteractsWithData;
    use Macroable;

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
        if (is_null($key)) {
            return $this->arguments;
        }

        return $this->arguments[$key] ?? $default;
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

    /**
     * @param  array<string, mixed>  $state
     */
    public function setState(array $state): void
    {
        $this->state = $state;
    }

    public function remember(string $key, Closure $callback): mixed
    {
        if (! array_key_exists($key, $this->state)) {
            $this->state[$key] = $callback();
        }

        return $this->state[$key];
    }

    /**
     * @param  Closure(JsonSchema): array<string, mixed>|array<string, mixed>  $schema
     */
    public function ask(string $message, Closure|array $schema, ?string $key = null): ElicitResponse
    {
        if (! $this->canAsk()) {
            throw new ElicitationNotSupportedException;
        }

        $requestedSchema = $schema instanceof Closure
            ? JsonSchemaFactory::object($schema)->toArray()
            : $schema;

        $this->assertFlatSchema($requestedSchema);

        $requestedSchema['properties'] = (object) ($requestedSchema['properties'] ?? []);

        return ElicitResponse::from($this->resolveInput([
            'method' => 'elicitation/create',
            'params' => [
                'mode' => 'form',
                'message' => $message,
                'requestedSchema' => $requestedSchema,
            ],
        ], $key));
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sample(array $messages, int $maxTokens, array $options = [], ?string $key = null): array
    {
        if (! $this->clientSupports('sampling')) {
            throw new SamplingNotSupportedException;
        }

        return $this->resolveInput([
            'method' => 'sampling/createMessage',
            'params' => [...$options, 'messages' => $messages, 'maxTokens' => $maxTokens],
        ], $key);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function roots(?string $key = null): array
    {
        if (! $this->clientSupports('roots')) {
            throw new RootsNotSupportedException;
        }

        $roots = $this->resolveInput([
            'method' => 'roots/list',
            'params' => [],
        ], $key)['roots'] ?? [];

        return is_array($roots) ? $roots : [];
    }

    public function canAsk(): bool
    {
        $elicitation = data_get($this->meta[MetaKey::CLIENT_CAPABILITIES->value] ?? [], 'elicitation');

        return $elicitation === [] || is_array(data_get($elicitation, 'form'));
    }

    public function clientSupports(string $capability): bool
    {
        $declared = data_get($this->meta[MetaKey::CLIENT_CAPABILITIES->value] ?? [], $capability);

        return is_bool($declared) ? $declared : is_array($declared);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    protected function assertFlatSchema(array $schema): void
    {
        foreach ((array) ($schema['properties'] ?? []) as $name => $property) {
            $type = is_array($property) ? $property['type'] ?? null : null;

            if ($type === 'object') {
                throw new InvalidArgumentException("The [{$name}] property must be a primitive. Form elicitation schemas may not nest objects.");
            }

            if ($type === 'array' && ! isset($property['items']['enum']) && ! isset($property['items']['anyOf'])) {
                throw new InvalidArgumentException("The [{$name}] property must be an enum array. Form elicitation schemas only allow arrays of enum values.");
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
