<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Elicitation\Fields;

interface ElicitField
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
