<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation\Fields;

class EnumField implements ElicitField
{
    protected ?string $description = null;

    protected ?string $default = null;

    protected bool $isRequired = false;

    /**
     * @var array<string, string>|null
     */
    protected ?array $titledOptions = null;

    /**
     * @param  array<int, string>  $options
     */
    public function __construct(
        protected string $title,
        protected array $options,
    ) {}

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param  array<string, string>  $options  Map of value => display title
     */
    public function titled(array $options): static
    {
        $this->titledOptions = $options;

        return $this;
    }

    public function default(string $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function required(): static
    {
        $this->isRequired = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->titledOptions !== null) {
            $schema = $this->buildTitledSchema();
        } else {
            $schema = [
                'type' => 'string',
                'title' => $this->title,
                'enum' => $this->options,
            ];
        }

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        if ($this->default !== null) {
            $schema['default'] = $this->default;
        }

        if ($this->isRequired) {
            $schema['_required'] = true;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildTitledSchema(): array
    {
        $oneOf = [];

        foreach ($this->titledOptions as $value => $title) {
            $oneOf[] = [
                'const' => $value,
                'title' => $title,
            ];
        }

        return [
            'title' => $this->title,
            'oneOf' => $oneOf,
        ];
    }
}
