<?php

declare(strict_types=1);

namespace Tests\Conformance\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class MultiRoundInputRequiredTool extends Tool
{
    protected string $name = 'test_input_required_result_multi_round';

    protected string $description = 'Tests a multi round input required result flow';

    public function handle(Request $request): Response
    {
        $name = $request->ask('Step 1: What is your name?', fn (JsonSchema $schema): array => [
            'name' => $schema->string()->required(),
        ], 'step1');

        $color = $request->ask('Step 2: What is your favorite color?', fn (JsonSchema $schema): array => [
            'color' => $schema->string()->required(),
        ], 'step2');

        return Response::text("{$name['name']} likes {$color['color']}.");
    }
}
