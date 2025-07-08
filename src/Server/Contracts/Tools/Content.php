<?php

namespace Laravel\Mcp\Server\Contracts\Tools;

interface Content
{
    /**
     * Convert the content to an array.
     */
    public function toArray(): array;
}
