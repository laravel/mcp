<?php

declare(strict_types=1);

namespace Tests\Conformance\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class TamperedStateInputRequiredTool extends Tool
{
    protected string $name = 'test_input_required_result_tampered_state';

    protected string $description = 'Tests rejection of a tampered request state';

    public function handle(Request $request): Response
    {
        $request->ask('Do you confirm?', fn (JsonSchema $schema): array => [
            'ok' => $schema->boolean()->required(),
        ], 'confirm');

        return Response::text('Confirmed.');
    }
}
