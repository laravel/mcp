<?php

declare(strict_types=1);

namespace Tests\Conformance\Prompts;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Tests\Conformance\Fixtures;

class PromptWithImage extends Prompt
{
    protected string $name = 'test_prompt_with_image';

    protected string $description = 'A prompt that includes image content';

    /**
     * @return array<int, Response>
     */
    public function handle(): array
    {
        return [
            Response::image(Fixtures::IMAGE_BASE64, 'image/png'),
            Response::text('Please analyze the image above.'),
        ];
    }
}
