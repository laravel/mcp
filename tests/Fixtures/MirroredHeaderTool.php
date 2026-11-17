<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class MirroredHeaderTool extends Tool
{
    protected string $description = 'This tool mirrors its arguments into Mcp-Param headers';

    public function handle(Request $request): Response
    {
        return Response::text("Region: {$request->get('region')}");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'region' => $schema->string()->required(),
            'replicas' => $schema->integer(),
        ];
    }

    public function toArray(): array
    {
        $tool = parent::toArray();

        $tool['inputSchema']['properties']['region']['x-mcp-header'] = 'Region';
        $tool['inputSchema']['properties']['replicas']['x-mcp-header'] = 'Replicas';

        return $tool;
    }
}
