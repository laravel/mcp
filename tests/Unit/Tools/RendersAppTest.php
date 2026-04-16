<?php

declare(strict_types=1);

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Ui\Enums\Visibility;

it('includes _meta.ui.resourceUri when RendersApp attribute is present', function (): void {
    $array = (new RendersAppSpecTool)->toArray();

    expect($array)->toHaveKey('_meta')
        ->and($array['_meta']['ui'])->toHaveKey('resourceUri')
        ->and($array['_meta']['ui']['resourceUri'])->toStartWith('ui://resources/');
});

it('includes default visibility when RendersApp attribute is present', function (): void {
    $array = (new RendersAppSpecTool)->toArray();

    expect($array['_meta']['ui']['visibility'])->toEqual(['model', 'app']);
});

it('supports custom visibility', function (): void {
    $array = (new AppOnlyTool)->toArray();

    expect($array['_meta']['ui']['visibility'])->toEqual(['app']);
});

it('does not include _meta.ui when RendersApp attribute is absent', function (): void {
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

class RendersAppSpecWidgetResource extends AppResource
{
    public function handle(Request $request): Response
    {
        return Response::text('<html></html>');
    }
}

#[RendersApp(resource: RendersAppSpecWidgetResource::class)]
class RendersAppSpecTool extends Tool
{
    protected string $description = 'A tool linked to a UI resource';

    public function handle(Request $request): Response
    {
        return Response::text('test');
    }
}

#[RendersApp(resource: RendersAppSpecWidgetResource::class, visibility: [Visibility::App])]
class AppOnlyTool extends Tool
{
    protected string $description = 'An app-only tool';

    public function handle(Request $request): Response
    {
        return Response::text('test');
    }
}
