<?php

declare(strict_types=1);

namespace Laravel\Mcp\Transport;

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Arr;
use Laravel\Mcp\Enums\RequestHeader;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Request;

class JsonRpcRequest
{
    protected const REQUEST_STATE_TTL = 3600;

    private ?string $scope = null;

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public int|string $id,
        public string $method,
        public array $params,
    ) {
        //
    }

    /**
     * @param  array{id: mixed, jsonrpc?: mixed, method?: mixed, params?: mixed}  $jsonRequest
     *
     * @throws JsonRpcException
     */
    public static function from(array $jsonRequest): static
    {
        $requestId = $jsonRequest['id'];

        if (! is_int($jsonRequest['id']) && ! is_string($jsonRequest['id'])) {
            throw new JsonRpcException('Invalid Request: The [id] member must be a string, number.', -32600, $requestId);
        }

        if (! isset($jsonRequest['jsonrpc']) || $jsonRequest['jsonrpc'] !== '2.0') {
            throw new JsonRpcException('Invalid Request: The [jsonrpc] member must be exactly [2.0].', -32600, $requestId);
        }

        if (! isset($jsonRequest['method']) || ! is_string($jsonRequest['method'])) {
            throw new JsonRpcException('Invalid Request: The [method] member is required and must be a string.', -32600, $requestId);
        }

        if (array_key_exists('params', $jsonRequest) && ! self::isObject($jsonRequest['params'])) {
            throw new JsonRpcException('Invalid params: The [params] member must be an object.', -32602, $requestId);
        }

        return new static(
            id: $requestId,
            method: $jsonRequest['method'],
            params: $jsonRequest['params'] ?? [],
        );
    }

    private static function isObject(mixed $value): bool
    {
        return is_array($value) && ($value === [] || ! array_is_list($value));
    }

    public function cursor(): ?string
    {
        return $this->get('cursor');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function meta(): ?array
    {
        return isset($this->params['_meta']) && self::isObject($this->params['_meta']) ? $this->params['_meta'] : null;
    }

    /**
     * @return array<string, string>
     */
    public function mirroredHeaders(): array
    {
        $headers = [RequestHeader::METHOD->value => $this->method];

        if (($name = $this->name()) !== null) {
            $headers[RequestHeader::NAME->value] = (string) new HeaderValue($name);
        }

        return $headers;
    }

    public function name(): ?string
    {
        $key = $this->nameKey();

        if ($key === null) {
            return null;
        }

        $name = $this->get($key);

        return is_string($name) ? $name : null;
    }

    public function requiresName(): bool
    {
        return $this->nameKey() !== null;
    }

    private function nameKey(): ?string
    {
        return match ($this->method) {
            'tools/call', 'prompts/get' => 'name',
            'resources/read' => 'uri',
            default => null,
        };
    }

    public function toRequest(): Request
    {
        if (array_key_exists('arguments', $this->params)) {
            $arguments = $this->params['arguments'];

            if (! self::isObject($arguments)) {
                throw new JsonRpcException('Invalid params: The [arguments] member must be an object.', -32602, $this->id);
            }
        } else {
            $arguments = [];
        }

        $payload = $this->requestState();

        $inputResponses = array_replace(
            is_array($payload['inputResponses'] ?? null) ? $payload['inputResponses'] : [],
            $this->inputResponses(),
        );

        foreach ($inputResponses as $key => $inputResponse) {
            if (! self::isObject($inputResponse)) {
                throw new JsonRpcException("Invalid params: The [inputResponses.{$key}] member must be an object.", -32602, $this->id);
            }
        }

        return new Request(
            arguments: $arguments,
            meta: $this->meta(),
            inputResponses: $inputResponses,
            state: is_array($payload['state'] ?? null) ? $payload['state'] : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function inputResponses(): array
    {
        $inputResponses = $this->get('inputResponses');

        return is_array($inputResponses) ? $inputResponses : [];
    }

    /**
     * @param  array<string, mixed>  $inputResponses
     * @param  array<string, mixed>  $state
     */
    public function encodeRequestState(array $inputResponses, array $state = []): string
    {
        return encrypt([
            'scope' => $this->scope(),
            'expiresAt' => now()->getTimestamp() + static::REQUEST_STATE_TTL,
            'inputResponses' => $inputResponses,
            'state' => $state,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestState(): array
    {
        $requestState = $this->get('requestState');

        if (! is_string($requestState)) {
            return [];
        }

        try {
            $payload = decrypt($requestState);
        } catch (DecryptException) {
            throw new JsonRpcException('Invalid params: The [requestState] member failed integrity verification.', -32602, $this->id);
        }

        if (! is_array($payload) || ! hash_equals($this->scope(), is_string($payload['scope'] ?? null) ? $payload['scope'] : '')) {
            throw new JsonRpcException('Invalid params: The [requestState] member was issued for a different request.', -32602, $this->id);
        }

        if (! is_int($payload['expiresAt'] ?? null) || $payload['expiresAt'] < now()->getTimestamp()) {
            throw new JsonRpcException('Invalid params: The [requestState] member has expired.', -32602, $this->id);
        }

        return $payload;
    }

    private function scope(): string
    {
        if ($this->scope !== null) {
            return $this->scope;
        }

        $user = call_user_func(Container::getInstance()->make('auth')->userResolver());
        $arguments = $this->get('arguments');

        return $this->scope = hash('sha256', json_encode([
            $this->method,
            $this->get('name'),
            $this->get('uri'),
            self::canonicalize(is_array($arguments) ? $arguments : []),
            $user instanceof Authenticatable ? $user->getAuthIdentifier() : null,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function canonicalize(array $arguments): array
    {
        ksort($arguments);

        return Arr::map($arguments, fn (mixed $value): mixed => is_array($value) ? self::canonicalize($value) : $value);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $this->id,
            'method' => $this->method,
            ...$this->params === [] ? [] : ['params' => $this->params],
        ];
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_UNESCAPED_UNICODE) ?: '';
    }
}
