<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Ui;

use Illuminate\Contracts\Support\Arrayable;
use Laravel\Mcp\Server\Ui\Enums\Permission;
use stdClass;

/**
 * @implements Arrayable<string, mixed>
 */
class Permissions implements Arrayable
{
    /** @var array<int, Permission> */
    protected array $enabled = [];

    public static function make(): static
    {
        return new static;
    }

    public function camera(): static
    {
        return $this->allow(Permission::Camera);
    }

    public function microphone(): static
    {
        return $this->allow(Permission::Microphone);
    }

    public function geolocation(): static
    {
        return $this->allow(Permission::Geolocation);
    }

    public function clipboardWrite(): static
    {
        return $this->allow(Permission::ClipboardWrite);
    }

    public function allow(Permission ...$permissions): static
    {
        array_push($this->enabled, ...$permissions);

        return $this;
    }

    /**
     * @return array<string, stdClass>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->enabled as $permission) {
            $result[$permission->value] = new stdClass;
        }

        return $result;
    }
}
