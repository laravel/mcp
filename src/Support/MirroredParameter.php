<?php

declare(strict_types=1);

namespace Laravel\Mcp\Support;

use Laravel\Mcp\Exceptions\MirroredParameterException;
use Laravel\Mcp\Transport\HeaderValue;

class MirroredParameter
{
    public function __construct(
        public readonly SchemaPath $path,
        public readonly string $name,
        public readonly MirroredParameterType $type,
    ) {
        //
    }

    public function header(): string
    {
        return MirroredParameters::PREFIX.$this->name;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function raw(array $arguments): mixed
    {
        return $this->path->valueIn($arguments);
    }

    public function stringify(mixed $value): ?string
    {
        return $this->type->stringify($value);
    }

    /**
     * @param  array<string, mixed>  $arguments
     *
     * @throws MirroredParameterException
     */
    public function value(array $arguments): ?HeaderValue
    {
        $value = $this->raw($arguments);

        if ($value === null) {
            return null;
        }

        $stringified = $this->stringify($value);

        if ($stringified === null) {
            throw new MirroredParameterException(
                "The [{$this->path}] argument cannot be mirrored into the [{$this->header()}] header as a [{$this->type->value}].",
            );
        }

        return new HeaderValue($stringified);
    }
}
