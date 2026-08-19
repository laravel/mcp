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
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Exceptions\ElicitationNotSupportedException;
use Laravel\Mcp\Exceptions\InputRequiredException;
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
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>|null  $meta
     * @param  array<string, mixed>  $inputResponses
     */
    public function __construct(
        protected array $arguments = [],
        protected ?array $meta = null,
        protected ?string $uri = null,
        protected array $inputResponses = [],
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
     * @param  Closure(JsonSchema): array<string, mixed>|array<string, mixed>  $schema
     */
    public function ask(string $message, Closure|array $schema, ?string $key = null): ElicitResponse
    {
        if (! $this->clientSupports('elicitation.form')) {
            throw new ElicitationNotSupportedException('form');
        }

        $requestedSchema = $schema instanceof Closure
            ? JsonSchemaFactory::object($schema)->toArray()
            : $schema;

        return $this->resolveElicitation([
            'mode' => 'form',
            'message' => $message,
            'requestedSchema' => $requestedSchema,
        ], $key ?? hash('sha256', $message.json_encode($requestedSchema)));
    }

    public function elicitUrl(string $message, string $url, ?string $key = null): ElicitResponse
    {
        if (! $this->clientSupports('elicitation.url')) {
            throw new ElicitationNotSupportedException('URL');
        }

        return $this->resolveElicitation([
            'mode' => 'url',
            'message' => $message,
            'url' => $url,
        ], $key ?? hash('sha256', $message.$url));
    }

    public function clientSupports(string $capability): bool
    {
        $capabilities = $this->meta[MetaKey::CLIENT_CAPABILITIES->value] ?? [];

        if ($capability === 'elicitation.form' && data_get($capabilities, 'elicitation') === []) {
            return true;
        }

        return is_array(data_get($capabilities, $capability));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function resolveElicitation(array $params, string $key): ElicitResponse
    {
        if (isset($this->inputResponses[$key]) && is_array($this->inputResponses[$key])) {
            return new ElicitResponse($this->inputResponses[$key]);
        }

        throw new InputRequiredException([
            $key => [
                'method' => 'elicitation/create',
                'params' => $params,
            ],
        ], $this->inputResponses);
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
