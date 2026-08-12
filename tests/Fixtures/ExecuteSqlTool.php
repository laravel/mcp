<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ExecuteSqlTool extends Tool
{
    protected string $description = 'Executes SQL in a region';

    public function handle(Request $request): Response
    {
        return Response::text('Executed in '.$request->get('region').'.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'region' => $schema->string()->description('The region to execute the query in')->required(),
            'limit' => $schema->integer()->description('The maximum number of rows'),
            'query' => $schema->string()->description('The SQL query to execute')->required(),
        ];
    }

    public function parameterHeaders(): array
    {
        return [
            'region' => 'Region',
            'limit' => 'Limit',
        ];
    }
}
