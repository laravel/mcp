<?php

namespace Laravel\Mcp\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Tests\Fixtures\ExampleServer;
use Laravel\Mcp\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_initialize_a_connection_over_http()
    {
        $response = $this->postJson('test-mcp', $this->initializeMessage());

        $response->assertStatus(200);

        $this->assertEquals($this->expectedInitializeResponse(), $response->json());
    }

    #[Test]
    public function it_can_list_resources_over_http()
    {
        $sessionId = $this->initializeHttpConnection();

        $response = $this->postJson(
            'test-mcp',
            $this->listResourcesMessage(),
            ['Mcp-Session-Id' => $sessionId],
        );

        $response->assertStatus(200);

        $this->assertEquals($this->expectedListResourcesResponse(), $response->json());
    }

    #[Test]
    public function it_can_read_a_resource_over_http()
    {
        $sessionId = $this->initializeHttpConnection();

        $response = $this->postJson(
            'test-mcp',
            $this->readResourceMessage(),
            ['Mcp-Session-Id' => $sessionId],
        );

        $response->assertStatus(200);

        $this->assertEquals($this->expectedReadResourceResponse(), $response->json());
    }

    #[Test]
    public function it_can_list_tools_over_http()
    {
        $sessionId = $this->initializeHttpConnection();

        $response = $this->postJson(
            'test-mcp',
            $this->listToolsMessage(),
            ['Mcp-Session-Id' => $sessionId],
        );

        $response->assertStatus(200);

        $this->assertEquals($this->expectedListToolsResponse(), $response->json());
    }

    #[Test]
    public function it_can_call_a_tool_over_http()
    {
        $sessionId = $this->initializeHttpConnection();

        $response = $this->postJson(
            'test-mcp',
            $this->callToolMessage(),
            ['Mcp-Session-Id' => $sessionId],
        );

        $response->assertStatus(200);

        $this->assertEquals($this->expectedCallToolResponse(), $response->json());
    }

    #[Test]
    public function it_can_handle_a_ping_over_http()
    {
        $sessionId = $this->initializeHttpConnection();

        $response = $this->postJson(
            'test-mcp',
            $this->pingMessage(),
            ['Mcp-Session-Id' => $sessionId],
        );

        $response->assertStatus(200);

        $this->assertEquals($this->expectedPingResponse(), $response->json());
    }

    #[Test]
    public function it_can_stream_a_tool_response_over_http()
    {
        $sessionId = $this->initializeHttpConnection();

        $response = $this->postJson(
            'test-mcp',
            $this->callStreamingToolMessage(),
            ['Mcp-Session-Id' => $sessionId, 'Accept' => 'text/event-stream'],
        );

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');

        $content = $response->streamedContent();
        $messages = $this->parseJsonRpcMessagesFromSseStream($content);

        $this->assertEquals($this->expectedStreamingToolResponse(), $messages);
    }

    #[Test]
    public function it_can_initialize_a_connection_over_stdio()
    {
        $process = new Process(['./vendor/bin/testbench', 'mcp:start', 'test-mcp']);
        $process->setInput(json_encode($this->initializeMessage()));

        $process->run();

        $output = json_decode($process->getOutput(), true);

        $this->assertEquals($this->expectedInitializeResponse(), $output);
    }

    #[Test]
    public function it_can_list_tools_over_stdio()
    {
        $process = new Process(['./vendor/bin/testbench', 'mcp:start', 'test-mcp']);
        $process->setInput(json_encode($this->listToolsMessage()));

        $process->run();

        $output = json_decode($process->getOutput(), true);

        $this->assertEquals($this->expectedListToolsResponse(), $output);
    }

    #[Test]
    public function it_can_call_a_tool_over_stdio()
    {
        $process = new Process(['./vendor/bin/testbench', 'mcp:start', 'test-mcp']);
        $process->setInput(json_encode($this->callToolMessage()));

        $process->run();

        $output = json_decode($process->getOutput(), true);

        $this->assertEquals($this->expectedCallToolResponse(), $output);
    }

    #[Test]
    public function it_can_handle_a_ping_over_stdio()
    {
        $process = new Process(['./vendor/bin/testbench', 'mcp:start', 'test-mcp']);
        $process->setInput(json_encode($this->pingMessage()));

        $process->run();

        $output = json_decode($process->getOutput(), true);

        $this->assertEquals($this->expectedPingResponse(), $output);
    }

    #[Test]
    public function it_can_stream_a_tool_response_over_stdio()
    {
        $process = new Process(['./vendor/bin/testbench', 'mcp:start', 'test-mcp']);
        $process->setInput(json_encode($this->callStreamingToolMessage()));

        $process->run();

        $output = $process->getOutput();
        $messages = $this->parseJsonRpcMessagesFromStdout($output);

        $this->assertEquals($this->expectedStreamingToolResponse(), $messages);
    }

    #[Test]
    public function it_can_list_dynamically_added_tools()
    {
        $sessionId = $this->initializeHttpConnection('test-mcp-dynamic-tools');

        $response = $this->postJson(
            'test-mcp-dynamic-tools',
            $this->listToolsMessage(),
            ['Mcp-Session-Id' => $sessionId],
        );

        $response->assertStatus(200);

        $this->assertEquals($this->expectedListToolsResponse(), $response->json());
    }

    private function initializeHttpConnection($handle = 'test-mcp')
    {
        $response = $this->postJson($handle, $this->initializeMessage());

        $sessionId = $response->headers->get('Mcp-Session-Id');

        $this->postJson($handle, $this->initializeNotificationMessage(), ['Mcp-Session-Id' => $sessionId]);

        return $sessionId;
    }

    private function initializeMessage(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 456,
            'method' => 'initialize',
            'params' => [],
        ];
    }

    private function expectedInitializeResponse(): array
    {
        $server = new ExampleServer;

        return [
            'jsonrpc' => '2.0',
            'id' => 456,
            'result' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => $server->capabilities,
                'serverInfo' => [
                    'name' => $server->serverName,
                    'version' => $server->serverVersion,
                ],
                'instructions' => $server->instructions,
            ],
        ];
    }

    private function listToolsMessage(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ];
    }

    private function expectedListToolsResponse(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'tools' => [
                    [
                        'name' => 'example-tool',
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
                    ],
                ],
            ],
        ];
    }

    private function listResourcesMessage(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'resources/list',
        ];
    }

    private function expectedListResourcesResponse(): array
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

    private function callToolMessage(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'example-tool',
                'arguments' => [
                    'name' => 'John Doe',
                ],
            ],
        ];
    }

    private function readResourceMessage(): array
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

    private function expectedReadResourceResponse(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 123,
            'result' => [
                'contents' => [[
                    'text' => '2025-07-02 12:00:00 Error: Something went wrong.',
                    'uri' => 'file://resources/last-log-line-resource',
                    'title' => 'Last Log Line Resource',
                    'mimeType' => 'text/plain',
                    'name' => 'last-log-line-resource',
                ]],
            ],
        ];
    }

    private function initializeNotificationMessage(): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ];
    }

    private function expectedCallToolResponse(): array
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

    private function pingMessage(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 789,
            'method' => 'ping',
        ];
    }

    private function expectedPingResponse(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 789,
            'result' => [],
        ];
    }

    private function callStreamingToolMessage(int $count = 2): array
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

    private function expectedStreamingToolResponse(int $count = 2): array
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

    private function expectedDeletedSessionErrorResponse(string|int $requestId): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $requestId,
            'error' => [
                'code' => -32601,
                'message' => 'Session not found or not initialized.',
            ],
        ];
    }

    private function parseJsonRpcMessagesFromSseStream(string $content): array
    {
        $messages = [];

        foreach (explode("\n\n", trim($content)) as $event) {
            if (empty($event)) {
                continue;
            }
            $messages[] = json_decode(trim(substr($event, strlen('data: '))), true);
        }

        return $messages;
    }

    private function parseJsonRpcMessagesFromStdout(string $output): array
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
}
