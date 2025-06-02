<?php

namespace Laravel\Mcp\Tests\Fixtures;

use Laravel\Mcp\Contracts\Tools\Tool;
use Laravel\Mcp\Tools\ToolInputSchema;
use Laravel\Mcp\Tools\ToolResponse;
use Laravel\Mcp\Tools\ToolNotification;

class StreamingTool implements Tool
{
    public function getName(): string
    {
        return 'streaming-tool';
    }

    public function getDescription(): string
    {
        return 'A tool that streams multiple responses.';
    }

    public function getInputSchema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->integer('count')
            ->description('Number of messages to stream.')
            ->required();
    }

    public function call(array $arguments): \Generator
    {
        $count = $arguments['count'] ?? 2;

        for ($i = 1; $i <= $count; $i++) {
            yield new ToolNotification('stream/progress', ['progress' => $i / $count * 100, 'message' => "Processing item {$i} of {$count}"]);
        }

        yield new ToolResponse("Finished streaming {$count} messages.");
    }
}
