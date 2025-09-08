<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Transport;

class JsonRpcProtocolError extends JsonRpcResponse
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public int $code,
        public string $message,
        public mixed $requestId = null,
        public ?array $data = null
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $error = [
            'code' => $this->code,
            'message' => $this->message,
        ];

        if ($this->data !== null) {
            $error['data'] = $this->data;
        }

        return [
            'jsonrpc' => '2.0',
            'error' => $error,
            'id' => $this->requestId,
        ];
    }
}
