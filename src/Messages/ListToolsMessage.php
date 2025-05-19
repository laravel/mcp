<?php

namespace Laravel\Mcp\Messages;

class ListToolsMessage
{
    public string $id;

    public function __construct(array $messageData)
    {
        $this->id = $messageData['id'];
    }
}
