<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitations;

use ArrayAccess;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Enums\ElicitationAction;
use LogicException;

/**
 * @implements ArrayAccess<string, mixed>
 */
class ElicitResponse implements ArrayAccess
{
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
        $content = Arr::get($response, 'content');

        return new static(
            ElicitationAction::tryFrom(is_string($action) ? $action : '') ?? ElicitationAction::Cancel,
            is_array($content) ? $content : [],
        );
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
