<?php

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
        return ResponseFactory::make(
            Response::text('Resource content with result meta')
        )->withMeta([
            'last_modified' => now()->toIso8601String(),
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
