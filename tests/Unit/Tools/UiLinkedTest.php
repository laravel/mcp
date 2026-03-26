<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\UiLinked;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\UiResource;

it('includes _meta.ui.resourceUri when UiLinked attribute is present', function (): void {
    $array = (new UiLinkedSpecTool)->toArray();

    expect($array)->toHaveKey('_meta')
        ->and($array['_meta']['ui'])->toHaveKey('resourceUri')
        ->and($array['_meta']['ui']['resourceUri'])->toStartWith('ui://resources/');
});

it('includes default visibility when UiLinked attribute is present', function (): void {
    $array = (new UiLinkedSpecTool)->toArray();

    expect($array['_meta']['ui']['visibility'])->toEqual(['model', 'app']);
});

it('supports custom visibility', function (): void {
    $array = (new AppOnlyTool)->toArray();

    expect($array['_meta']['ui']['visibility'])->toEqual(['app']);
});

it('does not include _meta.ui when UiLinked attribute is absent', function (): void {
    $tool = new class extends Tool
    {
        public function handle(Request $request): Response
        {
            return Response::text('test');
        }
    };

    $array = $tool->toArray();

    expect($array)->not->toHaveKey('_meta');
});

class UiLinkedSpecWidgetResource extends UiResource
{
    public function handle(Request $request): Response
    {
        return Response::text('<html></html>');
    }
}

#[UiLinked(resource: UiLinkedSpecWidgetResource::class)]
class UiLinkedSpecTool extends Tool
{
    protected string $description = 'A tool linked to a UI resource';

    public function handle(Request $request): Response
    {
        return Response::text('test');
    }
}

#[UiLinked(resource: UiLinkedSpecWidgetResource::class, visibility: ['app'])]
class AppOnlyTool extends Tool
{
    protected string $description = 'An app-only tool';

    public function handle(Request $request): Response
    {
        return Response::text('test');
    }
}
