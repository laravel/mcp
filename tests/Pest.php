<?php

use Tests\Fixtures\ExampleServer;
use Tests\TestCase;

uses(TestCase::class)
    ->beforeEach(function (): void {
        $directory = app_path('Mcp');
        $filesystem = new Illuminate\Filesystem\Filesystem;

        if ($filesystem->isDirectory($directory)) {
            $filesystem->deleteDirectory($directory);
        }

        config()->set('app.debug', true);
    })->in('Unit', 'Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function initializeMessage(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 456,
        'method' => 'initialize',
        'params' => [],
    ];
}

function expectedInitializeResponse(): array
{
    $server = new ExampleServer(new \Tests\Fixtures\ArrayTransport);

    [
        $capabilities,
        $name,
        $version,
        $instructions,
    ] = (fn (): array => [
        $this->capabilities,
        $this->name,
        $this->version,
        $this->instructions,
    ])->call($server);

    return [
        'jsonrpc' => '2.0',
        'id' => 456,
        'result' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => $capabilities,
            'serverInfo' => [
                'name' => $name,
                'version' => $version,
            ],
            'instructions' => $instructions,
        ],
    ];
}

function listToolsMessage(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ];
}

function expectedListToolsResponse(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'tools' => [
                [
                    'name' => 'say-hi-tool',
                    'description' => 'This tool says hello to a person',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'The name of the person to greet',
                            ],
                        ],
                        'required' => ['name'],
                    ],
                    'annotations' => [],
                    'title' => 'Say Hi Tool',
                ],
                [
                    'name' => 'streaming-tool',
                    'description' => 'A tool that streams multiple responses.',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'count' => [
                                'type' => 'integer',
                                'description' => 'Number of messages to stream.',
                            ],
                        ],
                        'required' => ['count'],
                    ],
                    'annotations' => [],
                    'title' => 'Streaming Tool',
                ],
            ],
        ],
    ];
}

function listResourcesMessage(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'resources/list',
    ];
}

function expectedListResourcesResponse(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'resources' => [
                [
                    'name' => 'last-log-line-resource',
                    'title' => 'Last Log Line Resource',
                    'description' => 'The last line of the log file',
                    'uri' => 'file://resources/last-log-line-resource',
                    'mimeType' => 'text/plain',
                ],
                [
                    'name' => 'daily-plan-resource',
                    'title' => 'Daily Plan Resource',
                    'description' => 'The plan for the day',
                    'uri' => 'file://resources/daily-plan.md',
                    'mimeType' => 'text/markdown',
                ],
                [
                    'name' => 'recent-meeting-recording-resource',
                    'title' => 'Recent Meeting Recording Resource',
                    'description' => 'The most recent meeting recording',
                    'uri' => 'file://resources/recent-meeting-recording.mp4',
                    'mimeType' => 'video/mp4',
                ],
            ],
        ],
    ];
}

function callToolMessage(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'say-hi-tool',
            'arguments' => [
                'name' => 'John Doe',
            ],
        ],
    ];
}

function readResourceMessage(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 123,
        'method' => 'resources/read',
        'params' => [
            'uri' => 'file://resources/last-log-line-resource',
        ],
    ];
}

function expectedReadResourceResponse(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 123,
        'result' => [
            'contents' => [[
                'text' => '2025-07-02 12:00:00 Error: Something went wrong.',
                'uri' => 'file://resources/last-log-line-resource',
                'mimeType' => 'text/plain',
            ]],
        ],
    ];
}

function initializeNotificationMessage(): array
{
    return [
        'jsonrpc' => '2.0',
        'method' => 'notifications/initialized',
    ];
}

function expectedCallToolResponse(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'result' => [
            'content' => [[
                'type' => 'text',
                'text' => 'Hello, John Doe!',
            ]],
            'isError' => false,
        ],
    ];
}

function pingMessage(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 789,
        'method' => 'ping',
    ];
}

function expectedPingResponse(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 789,
        'result' => [],
    ];
}

function callStreamingToolMessage(int $count = 2): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => [
            'name' => 'streaming-tool',
            'arguments' => [
                'count' => $count,
            ],
        ],
    ];
}

function expectedStreamingToolResponse(int $count = 2): array
{
    $messages = [];

    for ($i = 1; $i <= $count; $i++) {
        $messages[] = [
            'jsonrpc' => '2.0',
            'method' => 'stream/progress',
            'params' => ['progress' => $i / $count * 100, 'message' => "Processing item {$i} of {$count}"],
        ];
    }

    $messages[] = [
        'jsonrpc' => '2.0',
        'id' => 2,
        'result' => [
            'content' => [['type' => 'text', 'text' => "Finished streaming {$count} messages."]],
            'isError' => false,
        ],
    ];

    return $messages;
}

function parseJsonRpcMessagesFromSseStream(string $content): array
{
    $messages = [];

    foreach (explode("\n\n", trim($content)) as $event) {
        $event = trim($event);

        if ($event === '') {
            continue;
        }

        $messages[] = json_decode(trim(substr($event, strlen('data: '))), true);
    }

    return $messages;
}

function parseJsonRpcMessagesFromStdout(string $output): array
{
    $jsonMessages = array_filter(explode("\n", trim($output)));

    $messages = [];

    foreach ($jsonMessages as $jsonMessage) {
        if (empty($jsonMessage)) {
            continue;
        }

        $messages[] = json_decode($jsonMessage, true);
    }

    return $messages;
}
