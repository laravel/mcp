<?php

declare(strict_types=1);

namespace Tests\Conformance\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CustomHeaderTool extends Tool
{
    protected string $name = 'test_custom_header';

    protected string $description = 'Tests custom Mcp-Param header mirroring';

    public function handle(Request $request): Response
    {
        return Response::text("Region: {$request->get('region')}");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'region' => $schema->string()->description('Deployment region')->required(),
        ];
    }

    public function toArray(): array
    {
        $tool = parent::toArray();

        $tool['inputSchema']['properties']['region']['x-mcp-header'] = 'Region';

        return $tool;
    }
}
