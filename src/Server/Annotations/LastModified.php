<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Annotations;

use Attribute;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS)]
class LastModified extends Annotation
{
    public function __construct(public string $value)
    {
        try {
            new DateTimeImmutable($value);
        } catch (Exception $exception) {
            throw new InvalidArgumentException("LastModified must be a valid ISO 8601 timestamp, got '{$value}'", $exception->getCode(), previous: $exception);
        }
    }

    public function key(): string
    {
        return 'lastModified';
    }
}
