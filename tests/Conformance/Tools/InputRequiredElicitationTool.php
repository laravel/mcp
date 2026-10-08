<?php

declare(strict_types=1);

namespace Tests\Conformance\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class InputRequiredElicitationTool extends Tool
{
    protected string $name = 'test_input_required_result_elicitation';

    protected string $description = 'Tests elicitation through an input required result';

    public function handle(Request $request): Response
    {
        $response = $request->ask('What is your name?', fn (JsonSchema $schema): array => [
            'name' => $schema->string()->required(),
        ], 'user_name');

        return Response::text("Hello, {$response['name']}!");
    }
}
