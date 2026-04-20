<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Concerns\HasMeta;
use Laravel\Mcp\Server\Concerns\ReadsAttributes;

/**
 * @implements Arrayable<string, mixed>
 */
abstract class Primitive implements Arrayable
{
    use HasMeta;
    use ReadsAttributes;

    protected string $name = '';

    protected string $title = '';

    protected string $description = '';

    public function name(): string
    {
        $attribute = $this->resolveAttribute(Name::class);

        return $attribute !== null
            ? $attribute->value
            : ($this->name !== '' ? $this->name : Str::kebab(class_basename($this)));
    }

    public function title(): string
    {
        $attribute = $this->resolveAttribute(Title::class);

        return $attribute !== null
            ? $attribute->value
            : ($this->title !== '' ? $this->title : Str::headline(class_basename($this)));
    }

    public function description(): string
    {
        $attribute = $this->resolveAttribute(Description::class);

        return $attribute !== null
            ? $attribute->value
            : ($this->description !== '' ? $this->description : Str::headline(class_basename($this)));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function meta(): ?array
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
