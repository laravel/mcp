<?php

namespace Tests\Fixtures;

use Generator;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolNotification;
use Laravel\Mcp\Server\Tools\ToolResult;

class StreamingTool extends Tool
{
    protected string $description = 'A tool that streams multiple responses.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'count' => $schema->integer()
                ->description('Number of messages to stream.')
                ->required(),
        ];
    }

    public function handle(array $arguments): Generator
    {
        $count = $arguments['count'] ?? 2;

        for ($i = 1; $i <= $count; $i++) {
            yield new ToolNotification('stream/progress', ['progress' => $i / $count * 100, 'message' => "Processing item {$i} of {$count}"]);
        }

        yield ToolResult::text("Finished streaming {$count} messages.");
    }
}
