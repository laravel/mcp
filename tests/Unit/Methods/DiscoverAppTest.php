<?php

declare(strict_types=1);

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Resource;
use Tests\Fixtures\ArrayTransport;

it('advertises the ui extension when app resources are registered', function (): void {
    $server = new class(new ArrayTransport) extends Server
    {
        protected array $resources = [
            AutoDetectAppResource::class,
        ];
    };

    $server->start();

    $context = $server->createContext();

    expect($context->serverCapabilities['extensions'])->toHaveKey('io.modelcontextprotocol/ui');
    expect($server->hasExtension('io.modelcontextprotocol/ui'))->toBeTrue();
});

it('advertises no extensions when only regular resources are registered', function (): void {
    $server = new class(new ArrayTransport) extends Server
    {
        protected array $resources = [
            RegularResource::class,
        ];
    };

    $server->start();

    $context = $server->createContext();

    expect($context->serverCapabilities)->not->toHaveKey('extensions');
    expect($server->hasExtension('io.modelcontextprotocol/ui'))->toBeFalse();
});

it('advertises an extension with its settings object', function (): void {
    $server = new class(new ArrayTransport) extends Server {};

    $server->addExtension('io.modelcontextprotocol/ui', ['mimeTypes' => ['text/html;profile=mcp-app']]);
    $server->addExtension('com.example/other');

    $extensions = $server->createContext()->serverCapabilities['extensions'];

    expect($extensions['io.modelcontextprotocol/ui'])->toBe(['mimeTypes' => ['text/html;profile=mcp-app']]);
    expect($extensions['com.example/other'])->toEqual((object) []);
});

class AutoDetectAppResource extends AppResource
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
