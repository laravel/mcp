<?php

namespace Laravel\Mcp\Mcp\Transport;

class JsonRpcResponse
{
    public static function create($id, array $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }
}
