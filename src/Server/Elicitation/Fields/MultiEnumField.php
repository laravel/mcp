<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation\Fields;

class MultiEnumField implements ElicitField
{
    protected ?string $description = null;

    protected ?int $minItems = null;

    protected ?int $maxItems = null;

    /**
     * @var array<int, string>|null
     */
    protected ?array $default = null;

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

    public function minItems(int $minItems): static
    {
        $this->minItems = $minItems;

        return $this;
    }

    public function maxItems(int $maxItems): static
    {
        $this->maxItems = $maxItems;

        return $this;
    }

    /**
     * @param  array<int, string>  $default
     */
    public function default(array $default): static
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
        $items = $this->titledOptions !== null
            ? $this->buildTitledItems()
            : ['type' => 'string', 'enum' => $this->options];

        $schema = [
            'type' => 'array',
            'title' => $this->title,
            'items' => $items,
        ];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        if ($this->minItems !== null) {
            $schema['minItems'] = $this->minItems;
        }

        if ($this->maxItems !== null) {
            $schema['maxItems'] = $this->maxItems;
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
    protected function buildTitledItems(): array
    {
        $anyOf = [];

        foreach ($this->titledOptions as $value => $title) {
            $anyOf[] = [
                'const' => $value,
                'title' => $title,
            ];
        }

        return ['anyOf' => $anyOf];
    }
}
