<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\ResourceTemplate;
use Laravel\Mcp\Support\UriTemplate;

class ExampleResourceTemplate extends ResourceTemplate
{
    protected string $description = 'Example resource template for testing';

    protected string $mimeType = 'text/plain';

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('file://example/{id}');
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('id');

        return Response::text("Example resource: {$id}");
    }
}
