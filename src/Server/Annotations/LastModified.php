<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Annotations;

use Attribute;
use DateTime;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS)]
class LastModified extends Annotation
{
    public function __construct(public string $value)
    {
        $formats = [
            DateTime::ATOM,
            'Y-m-d\TH:i:sO',
            'Y-m-d\\TH:i:sP',
        ];

        foreach ($formats as $format) {
            $dateTime = DateTime::createFromFormat($format, $value);
            if ($dateTime !== false) {
                return;
            }
        }

        throw new InvalidArgumentException(
            "LastModified must be a valid ISO 8601 timestamp, got '{$value}'"
        );
    }

    public function key(): string
    {
        return 'lastModified';
    }
}
