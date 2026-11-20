<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;

class RequestMetaServer extends Server
{
    protected array $tools = [
        CapabilityAwareTool::class,
    ];
}

class CapabilityAwareTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $meta = $request->meta() ?? [];

        return Response::structured([
            'capabilities' => $meta[MetaKey::CLIENT_CAPABILITIES->value] ?? null,
            'hasProgressToken' => array_key_exists('progressToken', $meta),
        ]);
    }
}

class CapabilityAwareCall extends Tool
{
    public function __construct(private readonly Tool $tool) {}

    public function name(): string
    {
        return $this->tool->name();
    }

    public function toMethodCall(): array
    {
        return [
            ...$this->tool->toMethodCall(),
            '_meta' => [MetaKey::CLIENT_CAPABILITIES->value => ['extensions' => ['io.modelcontextprotocol/ui' => []]]],
        ];
    }
}

it('keeps the meta from the method call alongside the progress token', function (): void {
    RequestMetaServer::tool(new CapabilityAwareCall(new CapabilityAwareTool))
        ->assertStructuredContent([
            'capabilities' => ['extensions' => ['io.modelcontextprotocol/ui' => []]],
            'hasProgressToken' => true,
        ]);
});

it('declares form elicitation, sampling and roots by default', function (): void {
    RequestMetaServer::tool(CapabilityAwareTool::class)
        ->assertStructuredContent([
            'capabilities' => ['elicitation' => ['form' => []], 'sampling' => [], 'roots' => []],
            'hasProgressToken' => true,
        ]);
});

it('sends the client capabilities the test declares', function (): void {
    RequestMetaServer::withClientCapabilities(['sampling' => ['tools' => []]])
        ->tool(CapabilityAwareTool::class)
        ->assertStructuredContent([
            'capabilities' => ['sampling' => ['tools' => []]],
            'hasProgressToken' => true,
        ]);
});

it('prefers the method call meta over the declared client capabilities', function (): void {
    RequestMetaServer::withClientCapabilities(['sampling' => []])
        ->tool(new CapabilityAwareCall(new CapabilityAwareTool))
        ->assertStructuredContent([
            'capabilities' => ['extensions' => ['io.modelcontextprotocol/ui' => []]],
            'hasProgressToken' => true,
        ]);
});
