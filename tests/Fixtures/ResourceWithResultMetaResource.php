<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Resource;

class ResourceWithResultMetaResource extends Resource
{
    public function description(): string
    {
        return 'Resource with result-level meta';
    }

    public function handle(): ResponseFactory
    {
        return Response::make(
            Response::text('Resource content with result meta')
        )->withMeta([
            'last_modified' => '2025-01-01',
            'version' => '1.0',
        ]);
    }

    public function uri(): string
    {
        return 'file://resources/with-result-meta.txt';
    }

    public function mimeType(): string
    {
        return 'text/plain';
    }
}
