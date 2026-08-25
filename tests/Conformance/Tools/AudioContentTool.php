<?php

declare(strict_types=1);

namespace Tests\Conformance\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Tests\Conformance\Fixtures;

class AudioContentTool extends Tool
{
    protected string $name = 'test_audio_content';

    protected string $description = 'Tests audio content response';

    public function handle(Request $request): Response
    {
        return Response::audio(Fixtures::AUDIO_BASE64, 'audio/wav');
    }
}
