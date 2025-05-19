<?php

namespace Laravel\Mcp\Messages;

class InitializeMessage
{
    public string $id;

    public function __construct(array $messageData)
    {
        $this->id = $messageData['id'];
    }
}
