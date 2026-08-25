<?php

declare(strict_types=1);

namespace Tests\Conformance\Resources;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use Tests\Conformance\Fixtures;

class StaticBinaryResource extends Resource
{
    protected string $name = 'static-binary';

    protected string $uri = 'test://static-binary';

    protected string $mimeType = 'image/png';

    protected string $description = 'A static binary resource (image) for testing';

    public function handle(): Response
    {
        return Response::blob(base64_decode(Fixtures::IMAGE_BASE64, true));
    }
}
