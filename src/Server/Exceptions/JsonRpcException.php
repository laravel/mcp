<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Exceptions;

use BadMethodCallException;
use Exception;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

/**
 * @method static self parseError(string $message, mixed $requestId = null, array $data = null)
 * @method static self invalidRequest(string $message, mixed $requestId = null, array $data = null)
 * @method static self methodNotFound(string $message, mixed $requestId = null, array $data = null)
 * @method static self invalidParams(string $message, mixed $requestId = null, array $data = null)
 * @method static self internalError(string $message, mixed $requestId = null, array $data = null)
 */
class JsonRpcException extends Exception
{
    public const PARSE_ERROR = -32700;

    public const INVALID_REQUEST = -32600;

    public const METHOD_NOT_FOUND = -32601;

    public const INVALID_PARAMS = -32602;

    public const INTERNAL_ERROR = -32603;

    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        string $message,
        int $code,
        protected mixed $requestId = null,
        protected ?array $data = null
    ) {
        parent::__construct($message, $code);
    }

    public function toJsonRpcResponse(): JsonRpcResponse
    {
        return JsonRpcResponse::error(
            id: $this->requestId,
            code: $this->getCode(),
            message: $this->getMessage(),
            data: $this->data,
        );
    }

    public static function __callStatic($name, $arguments)
    {
        $code = match ($name) {
            'parseError' => self::PARSE_ERROR,
            'invalidRequest' => self::INVALID_REQUEST,
            'methodNotFound' => self::METHOD_NOT_FOUND,
            'invalidParams' => self::INVALID_PARAMS,
            'internalError' => self::INTERNAL_ERROR,
            default => throw new BadMethodCallException("Undefined method [{$name}] called on JsonRpcException."),
        };

        return new static(
            message: $arguments[0] ?? '',
            code: $code,
            requestId: $arguments[1] ?? null,
            data: $arguments[2] ?? null,
        );
    }
}
