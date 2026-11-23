<?php

declare(strict_types=1);

use Laravel\Mcp\Request;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Transport\JsonRpcResponse;
use PHPUnit\Framework\AssertionFailedError;

class ErrorCodeServer extends Server
{
    protected array $tools = [
        ErrorCodeTool::class,
    ];
}

class ErrorCodeTool extends Tool
{
    public function handle(Request $request): string
    {
        return 'ok';
    }
}

class UnregisteredErrorCodeTool extends ErrorCodeTool {}

it('asserts the json rpc error code', function (): void {
    ErrorCodeServer::tool(UnregisteredErrorCodeTool::class)->assertErrorCode(-32602);
});

it('asserts the json rpc error data', function (): void {
    $response = new TestResponse(new ErrorCodeTool, JsonRpcResponse::error(1, -32021, 'Missing capability.', [
        'requiredCapabilities' => ['sampling' => (object) []],
    ]));

    $response->assertErrorCode(-32021, ['requiredCapabilities' => ['sampling' => []]]);

    expect(fn (): TestResponse => $response->assertErrorCode(-32021, ['requiredCapabilities' => ['roots' => []]]))
        ->toThrow(AssertionFailedError::class, 'The response does not carry the expected error data.');
});

it('fails when the error code differs', function (): void {
    expect(fn (): TestResponse => ErrorCodeServer::tool(UnregisteredErrorCodeTool::class)->assertErrorCode(-32601))
        ->toThrow(AssertionFailedError::class, 'The response does not carry the expected error code.');
});

it('fails when the response is not an error', function (): void {
    expect(fn (): TestResponse => ErrorCodeServer::tool(ErrorCodeTool::class)->assertErrorCode(-32602))
        ->toThrow(AssertionFailedError::class, 'The response is not a JSON-RPC error.');
});
