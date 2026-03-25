<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\UiResource;
use Tests\Fixtures\ArrayTransport;

it('auto-detects ui capability when ui resources are registered', function (): void {
    $server = new class(new ArrayTransport) extends Server
    {
        protected array $resources = [
            AutoDetectUiResource::class,
        ];
    };

    $server->start();

    $context = $server->createContext();

    expect($context->serverCapabilities)->toHaveKey('io.modelcontextprotocol/ui');
});

it('does not include ui capability when only regular resources are registered', function (): void {
    $server = new class(new ArrayTransport) extends Server
    {
        protected array $resources = [
            RegularResource::class,
        ];
    };

    $server->start();

    $context = $server->createContext();

    expect($context->serverCapabilities)->not->toHaveKey('io.modelcontextprotocol/ui');
});

class AutoDetectUiResource extends UiResource
{
    public function handle(Request $request): Response
    {
        return Response::text('<html></html>');
    }
}

class RegularResource extends Resource
{
    protected string $uri = 'file://resources/regular';

    protected string $mimeType = 'text/plain';

    public function handle(): string
    {
        return 'plain content';
    }
}
