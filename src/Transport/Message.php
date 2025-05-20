<?php

namespace Laravel\Mcp\Transport;

class Message
{
    public function __construct(
        public int $id,
        public array $params,
    ) {
    }
}
