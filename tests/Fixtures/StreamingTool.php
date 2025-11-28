<?php

namespace Tests\Fixtures;

use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

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

    public function handle(Request $request): Generator
    {
        $count = $request->integer('count', 2);

        for ($i = 1; $i <= $count; $i++) {
            yield Response::notification('stream/progress', ['progress' => $i / $count * 100, 'message' => "Processing item {$i} of {$count}"]);
        }

        yield Response::text("Finished streaming {$count} messages.");
    }
}
