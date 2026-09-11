<?php

declare(strict_types=1);

namespace Tests\Conformance\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class RequestStateInputRequiredTool extends Tool
{
    protected string $name = 'test_input_required_result_request_state';

    protected string $description = 'Tests round tripping the request state';

    public function handle(Request $request): Response
    {
        $request->ask('Please confirm', fn (JsonSchema $schema): array => [
            'ok' => $schema->boolean()->required(),
        ], 'confirm');

        return Response::text('state-ok');
    }
}
