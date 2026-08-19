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
     * @param  array{action?: mixed, content?: array<string, mixed>}  $response
     */
    public function __construct(protected array $response)
    {
        $content = Arr::get($this->response, 'content');

        $this->response['content'] = is_array($content) ? $content : [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->content()[$key] ?? $default;
    }

    public function action(): ?ElicitationAction
    {
        $action = Arr::get($this->response, 'action');

        return is_string($action) ? ElicitationAction::tryFrom($action) : null;
    }

    public function accepted(): bool
    {
        return $this->action() === ElicitationAction::Accept;
    }

    public function declined(): bool
    {
        return $this->action() === ElicitationAction::Decline;
    }

    public function cancelled(): bool
    {
        return $this->action() === ElicitationAction::Cancel;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public function validate(array $rules): array
    {
        return Validator::validate($this->content(), $rules);
    }

    /**
     * @return array<string, mixed>
     */
    protected function content(): array
    {
        $action = $this->action();

        if ($action !== ElicitationAction::Accept) {
            throw new LogicException(sprintf(
                'The elicitation was not accepted. Check accepted() before reading the response [action: %s].',
                $action instanceof ElicitationAction ? $action->value : 'missing',
            ));
        }

        return Arr::get($this->response, 'content', []);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->accepted() && isset($this->response['content'][$offset]);
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
