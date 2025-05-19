<?php

namespace Laravel\Mcp\Messages;

class CallToolMessage
{
    public string $id;
    public string $toolName;
    public array $toolArguments;

    public function __construct(array $messageData)
    {
        $this->id = $messageData['id'];
        $this->toolName = $messageData['params']['name'];
        $this->toolArguments = $messageData['params']['arguments'];
    }
}
