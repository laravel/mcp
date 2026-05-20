<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

class UriTemplateSummaryResource extends Resource implements HasUriTemplate
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
