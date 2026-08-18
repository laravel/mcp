<?php

declare(strict_types=1);

namespace Tests\Conformance\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

class PromptWithEmbeddedResource extends Prompt
{
    protected string $name = 'test_prompt_with_embedded_resource';

    protected string $description = 'A prompt that includes an embedded resource';

    /**
     * @return array<int, Response>
     */
    public function handle(Request $request): array
    {
        return [
            Response::resourceLink(
                uri: $request->get('resourceUri'),
                name: 'embedded-resource',
                description: 'Embedded resource content for testing.',
            ),
            Response::text('Please process the embedded resource above.'),
        ];
    }

    public function arguments(): array
    {
        return [
            new Argument('resourceUri', 'URI of the resource to embed', true),
        ];
    }
}
