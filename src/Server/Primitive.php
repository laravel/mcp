<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

/**
 * @implements Arrayable<string, mixed>
 */
abstract class Primitive implements Arrayable
{
    protected string $name = '';

    protected string $title = '';

    protected string $description = '';

    /**
     * @var array<string, mixed>
     */
    protected array $meta = [];

    public function name(): string
    {
        return $this->name === ''
            ? Str::kebab(class_basename($this))
            : $this->name;
    }

    public function title(): string
    {
        return $this->title === ''
            ? Str::headline(class_basename($this))
            : $this->title;
    }

    public function description(): string
    {
        return $this->description === ''
            ? Str::headline(class_basename($this))
            : $this->description;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    public function eligibleForRegistration(): bool
    {
        if (method_exists($this, 'shouldRegister')) {
            return Container::getInstance()->call([$this, 'shouldRegister']);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function toMethodCall(): array;

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;
}
