<?php

declare(strict_types=1);

namespace Tests\Conformance\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Tests\Conformance\Fixtures;

class ImageContentTool extends Tool
{
    protected string $name = 'test_image_content';

    protected string $description = 'Tests image content response';

    public function handle(Request $request): Response
    {
        return Response::image(Fixtures::IMAGE_BASE64, 'image/png');
    }
}
