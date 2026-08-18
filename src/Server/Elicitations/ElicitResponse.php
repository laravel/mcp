<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitations;

use ArrayAccess;
use Illuminate\Support\Facades\Validator;
use LogicException;

/**
 * @implements ArrayAccess<string, mixed>
 */
class ElicitResponse implements ArrayAccess
{
    /**
     * @param  array{action?: mixed, content?: array<string, mixed>}  $response
     */
    public function __construct(protected array $response)
    {
        $this->response['content'] = is_array($this->response['content'] ?? null)
            ? $this->response['content']
            : [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->response['content'][$key] ?? $default;
    }

    public function accepted(): bool
    {
        return ($this->response['action'] ?? null) === 'accept';
    }

    public function declined(): bool
    {
        return ($this->response['action'] ?? null) === 'decline';
    }

    public function cancelled(): bool
    {
        return ($this->response['action'] ?? null) === 'cancel';
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public function validate(array $rules): array
    {
        return Validator::validate($this->response['content'] ?? [], $rules);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->response['content'][$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
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
