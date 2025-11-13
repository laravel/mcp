<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Prompt;

class PromptWithResultMetaPrompt extends Prompt
{
    protected string $description = 'Prompt with result-level meta';

    public function handle(): ResponseFactory
    {
        return Response::make(
            Response::text('Prompt instructions with result meta')->withMeta(['key' => 'value'])
        )->withMeta([
            'prompt_version' => '2.0',
            'last_updated' => now()->toDateString(),
        ]);
    }
}
