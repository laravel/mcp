<?php

declare(strict_types=1);

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

class SummaryResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('file://summary/{id}');
    }

    public function handle(Request $request): Response
    {
        $request->validate(['id' => 'required|string']);

        return Response::json([
            'id' => $request->get('id'),
            'uri' => $request->uri(),
        ]);
    }
}

class UserFileResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('file://users/{userId}/files/{fileId}');
    }

    public function handle(Request $request): Response
    {
        return Response::json([
            'userId' => $request->get('userId'),
            'fileId' => $request->get('fileId'),
            'uri' => $request->uri(),
        ]);
    }
}

class TemplateResourceServer extends Server
{
    protected array $resources = [
        SummaryResource::class,
        UserFileResource::class,
    ];
}

it('resolves URI template variables from arguments when testing resources', function (): void {
    $response = TemplateResourceServer::resource(SummaryResource::class, ['id' => 'abc']);

    $response->assertOk()
        ->assertSee('"id":"abc"')
        ->assertSee('"uri":"file://summary/abc"');
});

it('resolves multiple URI template variables when testing resources', function (): void {
    $response = TemplateResourceServer::resource(UserFileResource::class, [
        'userId' => '42',
        'fileId' => 'document.pdf',
    ]);

    $response->assertOk()
        ->assertSee('"userId":"42"')
        ->assertSee('"fileId":"document.pdf"')
        ->assertSee('"uri":"file://users/42/files/document.pdf"');
});
