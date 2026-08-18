<?php

declare(strict_types=1);

namespace Tests\Conformance\Prompts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;

class InputRequiredElicitationPrompt extends Prompt
{
    protected string $name = 'test_input_required_result_prompt';

    protected string $description = 'Tests elicitation through a prompt input required result';

    public function handle(Request $request): Response
    {
        $response = $request->ask('What context should the prompt use?', fn (JsonSchema $schema): array => [
            'context' => $schema->string()->required(),
        ], 'user_context');

        return Response::text("Use this context: {$response['context']}");
    }
}
