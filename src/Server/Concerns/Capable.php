<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Concerns;

use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;

trait Capable
{
    protected string $name = '';

    protected string $title = '';

    protected string $description = '';

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

    public function eligibleForRegistration(Request $request): bool
    {
        if (method_exists($this, 'shouldRegister')) {
            return Container::getInstance()->call([$this, 'shouldRegister'], [
                'request' => $request,
            ]);
        }

        return true;
    }
}
