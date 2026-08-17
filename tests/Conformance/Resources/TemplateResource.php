<?php

declare(strict_types=1);

namespace Tests\Conformance\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

class TemplateResource extends Resource implements HasUriTemplate
{
    protected string $name = 'template';

    protected string $mimeType = 'application/json';

    protected string $description = 'A resource template with parameter substitution';

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('test://template/{id}/data');
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('id');

        return Response::json([
            'id' => $id,
            'templateTest' => true,
            'data' => "Data for ID: {$id}",
        ]);
    }
}
