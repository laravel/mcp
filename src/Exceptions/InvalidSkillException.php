<?php

declare(strict_types=1);

namespace Laravel\Mcp\Exceptions;

use Laravel\Mcp\Server\Skill;
use RuntimeException;
use Throwable;

class InvalidSkillException extends RuntimeException
{
    public static function for(Skill $skill, Throwable $previous): self
    {
        if ($previous instanceof self) {
            return $previous;
        }

        return new self(sprintf('The skill [%s] is invalid. %s', $skill::class, $previous->getMessage()), previous: $previous);
    }
}
