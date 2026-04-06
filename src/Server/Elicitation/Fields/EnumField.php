<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation\Fields;

class EnumField extends AbstractElicitField
{
    protected ?string $default = null;

    /**
     * @var array<string, string>|null
     */
    protected ?array $titledOptions = null;

    /**
     * @param  array<int, string>  $options
     */
    public function __construct(
        string $title,
        protected array $options,
    ) {
        parent::__construct($title);
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
