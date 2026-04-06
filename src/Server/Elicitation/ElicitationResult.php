<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation;

class ElicitationResult
{
    protected ?string $elicitationId = null;

    /**
     * @param  array<string, mixed>|null  $content
     */
    public function __construct(
        protected string $action,
        protected ?array $content = null,
    ) {}

    public function accepted(): bool
    {
        return $this->action === 'accept';
    }

    public function declined(): bool
    {
        return $this->action === 'decline';
    }

    public function cancelled(): bool
    {
        return $this->action === 'cancel';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->content[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->content ?? [];
    }

    public function action(): string
    {
        return $this->action;
    }

    public function elicitationId(): ?string
    {
        return $this->elicitationId;
    }

    public function setElicitationId(string $id): void
    {
        $this->elicitationId = $id;
    }
}
