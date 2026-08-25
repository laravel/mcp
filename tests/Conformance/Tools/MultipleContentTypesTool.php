<?php

declare(strict_types=1);

namespace Tests\Conformance\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Tests\Conformance\Fixtures;

class MultipleContentTypesTool extends Tool
{
    protected string $name = 'test_multiple_content_types';

    protected string $description = 'Tests response with multiple content types';

    /**
     * @return array<int, Response>
     */
    public function handle(Request $request): array
    {
        return [
            Response::text('Multiple content types test:'),
            Response::image(Fixtures::IMAGE_BASE64, 'image/png'),
            Response::resourceLink(
                uri: 'test://mixed-content-resource',
                name: 'mixed-content-resource',
                mimeType: 'application/json',
            ),
        ];
    }
}
