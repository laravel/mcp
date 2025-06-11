<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Tools\Tool;
use Laravel\Mcp\Tools\ToolInputSchema;
use Laravel\Mcp\Tools\ToolResponse;
use Laravel\Mcp\Tools\ToolNotification;
use Generator;

class StreamingTool extends Tool
{
    public function description(): string
    {
        return 'A tool that streams multiple responses.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->integer('count')
            ->description('Number of messages to stream.')
            ->required();
    }

    public function handle(array $arguments): Generator
    {
        $count = $arguments['count'] ?? 2;

        for ($i = 1; $i <= $count; $i++) {
            yield new ToolNotification('stream/progress', ['progress' => $i / $count * 100, 'message' => "Processing item {$i} of {$count}"]);
        }

        yield new ToolResponse("Finished streaming {$count} messages.");
    }
}
