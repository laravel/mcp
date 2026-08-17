<?php

declare(strict_types=1);

namespace Tests\Conformance\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

class PromptWithArguments extends Prompt
{
    protected string $name = 'test_prompt_with_arguments';

    protected string $description = 'A prompt with required arguments';

    public function handle(Request $request): Response
    {
        $arg1 = $request->get('arg1');
        $arg2 = $request->get('arg2');

        return Response::text("Prompt with arguments: arg1=\"{$arg1}\", arg2=\"{$arg2}\"");
    }

    public function arguments(): array
    {
        return [
            new Argument('arg1', 'First test argument', true),
            new Argument('arg2', 'Second test argument', true),
        ];
    }
}
