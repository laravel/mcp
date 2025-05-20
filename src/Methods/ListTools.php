<?php

namespace Laravel\Mcp\Methods;

use Laravel\Mcp\Messages\ListToolsMessage;
use Laravel\Mcp\Server;
use Laravel\Mcp\Transport\JsonRpcResponse;

class ListTools
{
    public function handle(ListToolsMessage $message, Server $server): JsonRpcResponse
    {
        $toolList = collect($server->tools)->values()->map(function (string $toolClass) {
            $tool = new $toolClass();

            return [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'inputSchema' => $tool->getInputSchema()->toArray(),
            ];
        });

        return JsonRpcResponse::create($message->id, [
            'tools' => $toolList,
        ]);
    }
}
