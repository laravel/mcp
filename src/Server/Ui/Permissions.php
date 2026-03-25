<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Ui;

use Illuminate\Contracts\Support\Arrayable;
use Laravel\Mcp\Server\Ui\Enum\Permission;
use stdClass;

/**
 * @implements Arrayable<string, mixed>
 */
class Permissions implements Arrayable
{
    public function __construct(
        protected bool $camera = false,
        protected bool $microphone = false,
        protected bool $geolocation = false,
        protected bool $clipboardWrite = false,
    ) {
        //
    }

    public static function make(): static
    {
        return new static;
    }

    public function camera(bool $enabled = true): static
    {
        $this->camera = $enabled;

        return $this;
    }

    public function microphone(bool $enabled = true): static
    {
        $this->microphone = $enabled;

        return $this;
    }

    public function geolocation(bool $enabled = true): static
    {
        $this->geolocation = $enabled;

        return $this;
    }

    public function clipboardWrite(bool $enabled = true): static
    {
        $this->clipboardWrite = $enabled;

        return $this;
    }

    public function allow(Permission ...$permissions): static
    {
        foreach ($permissions as $permission) {
            match ($permission) {
                Permission::Camera => $this->camera = true,
                Permission::Microphone => $this->microphone = true,
                Permission::Geolocation => $this->geolocation = true,
                Permission::ClipboardWrite => $this->clipboardWrite = true,
            };
        }

        return $this;
    }

    /**
     * @return array<string, stdClass>
     */
    public function toArray(): array
    {
        return array_map(
            fn (): stdClass => new stdClass,
            array_filter([
                'camera' => $this->camera,
                'microphone' => $this->microphone,
                'geolocation' => $this->geolocation,
                'clipboardWrite' => $this->clipboardWrite,
            ]),
        );
    }
}
