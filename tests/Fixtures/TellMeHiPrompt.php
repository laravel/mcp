<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;

class TellMeHiPrompt extends Prompt
{
    protected string $description = 'Instructions for how too tell me hi';

    public function handle(): Response
    {
        return Response::text('Here are the instructions on how to tell me hi');
    }
}
