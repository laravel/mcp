<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\InteractsWithData;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Validation\ValidationException;

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
     */
    public function __construct(
        protected array $arguments = [],
        protected ?string $sessionId = null,
        protected ?array $meta = null,
        protected ?string $uri = null,
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

    public function sessionId(): ?string
    {
        return $this->sessionId;
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
     * @param  array<string, mixed>  $arguments
     */
    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }

    public function setSessionId(?string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function setMeta(?array $meta): void
    {
        $this->meta = $meta;
    }

    public function setUri(?string $uri): void
    {
        $this->uri = $uri;
    }
}
