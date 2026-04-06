<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation\Fields;

abstract class AbstractElicitField implements ElicitField
{
    protected ?string $description = null;

    protected bool $isRequired = false;

    public function __construct(protected string $title) {}

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function required(): static
    {
        $this->isRequired = true;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }
}
