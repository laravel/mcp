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

it('sends only a progress token when the method call has no meta', function (): void {
    RequestMetaServer::tool(CapabilityAwareTool::class)
        ->assertStructuredContent([
            'capabilities' => null,
            'hasProgressToken' => true,
        ]);
});
