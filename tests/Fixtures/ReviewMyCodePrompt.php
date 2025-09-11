<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;

class ReviewMyCodePrompt extends Prompt
{
    protected string $description = 'Instructions for how to review my code';

    public function handle(): Response
    {
        return Response::text('Here are the instructions on how to review my code');
    }
}
