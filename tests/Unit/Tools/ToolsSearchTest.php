<?php

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Schema\Implementation;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\ExecuteTools;
use Laravel\Mcp\Server\Tools\SearchTools;
use Laravel\Mcp\Server\Tools\ToolsSearch;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Tests\Fixtures\SayHiTool;
use Tests\Fixtures\StreamingTool;
use Tests\Fixtures\StructuredContentTool;

it('supports declaring a searchable tool group on the server', function (): void {
    $server = new class(new FakeTransporter) extends Server
    {
        protected array $tools = [
            StructuredContentTool::class,
        ];

        protected function boot(): void
        {
            $this->tools[] = ToolsSearch::for([
                SayHiTool::class,
            ]);
        }
    };

    $server->start();

    $context = $server->createContext();
    $tools = $context->tools();
    $searchTools = $tools->first(fn (Tool $tool): bool => $tool->name() === 'search_tools');

    expect($tools->map(fn (Tool $tool): string => $tool->name())->all())->toBe([
        'structured-content-tool',
        'search_tools',
        'execute_tools',
    ])->and($searchTools)->toBeInstanceOf(SearchTools::class);

    if (! $searchTools instanceof Tool) {
        throw new LogicException('The search tool was not registered.');
    }

    $result = callContextTool($context, $searchTools, ['query' => 'person name']);

    expect($result['payload']['tools'][0]['name'])->toBe('say-hi-tool');
});

it('searches hidden tools and returns their complete schemas', function (): void {
    $context = toolSearchContext([
        SayHiTool::class,
        StructuredContentTool::class,
    ]);
    $searchTools = toolFromContext($context, 'search_tools');

    $result = callContextTool($context, $searchTools, [
        'query' => 'person name',
    ]);

    expect($searchTools)->toBeInstanceOf(SearchTools::class)
        ->and($searchTools->name())->toBe('search_tools')
        ->and($result['isError'])->toBeFalse()
        ->and($result['payload'])->toEqual([
            'ok' => true,
            'tools' => [[
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
            ]],
            'hasMore' => false,
        ]);
});

it('includes tool annotations in search results', function (): void {
    $annotatedTool = new #[IsDestructive] class extends Tool
    {
        protected string $name = 'delete-thing-tool';

        protected string $description = 'Deletes a thing';

        public function handle(): Response
        {
            return Response::text('deleted');
        }
    };

    $context = toolSearchContext([$annotatedTool]);
    $searchTools = toolFromContext($context, 'search_tools');

    $result = callContextTool($context, $searchTools, ['query' => 'delete']);

    expect($result['payload']['tools'][0]['annotations'])->toBe(['destructiveHint' => true]);
});

it('supports fluent per-catalog limits', function (): void {
    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [ToolsSearch::for([SayHiTool::class])->maxToolCalls(1)->maxOutputBytes(256)],
        resources: [],
        prompts: [],
    );
    $executeTools = toolFromContext($context, 'execute_tools');

    $callLimit = callContextTool($context, $executeTools, [
        'calls' => [
            ['name' => 'say-hi-tool', 'arguments' => ['name' => 'One']],
            ['name' => 'say-hi-tool', 'arguments' => ['name' => 'Two']],
        ],
    ]);

    $outputLimit = callContextTool($context, $executeTools, [
        'calls' => [[
            'name' => 'say-hi-tool',
            'arguments' => ['name' => str_repeat('x', 256)],
        ]],
    ]);

    expect($callLimit['isError'])->toBeTrue()
        ->and($callLimit['content'][0]['text'])->toContain('The calls field must not have more than 1 items.')
        ->and($outputLimit['payload']['error']['kind'])->toBe('OutputLimitExceeded');
});

it('executes multiple independent tools synchronously', function (): void {
    $context = toolSearchContext([
        SayHiTool::class,
        StructuredContentTool::class,
    ]);
    $executeTools = toolFromContext($context, 'execute_tools');

    $result = callContextTool($context, $executeTools, [
        'calls' => [
            ['name' => 'say-hi-tool', 'arguments' => ['name' => 'Taylor']],
            ['name' => 'structured-content-tool', 'arguments' => []],
        ],
    ]);

    expect($executeTools)->toBeInstanceOf(ExecuteTools::class)
        ->and($executeTools->name())->toBe('execute_tools')
        ->and($result['isError'])->toBeFalse()
        ->and($result['payload'])->toBe([
            'ok' => true,
            'results' => [
                [
                    'name' => 'say-hi-tool',
                    'content' => [['type' => 'text', 'text' => 'Hello, Taylor!']],
                    'isError' => false,
                ],
                [
                    'name' => 'structured-content-tool',
                    'content' => [['type' => 'text', 'text' => '{"temperature":22.5,"conditions":"Partly cloudy","humidity":65}']],
                    'isError' => false,
                    'structuredContent' => [
                        'temperature' => 22.5,
                        'conditions' => 'Partly cloudy',
                        'humidity' => 65,
                    ],
                ],
            ],
        ]);
});

it('stops executing after the first tool error', function (): void {
    $calls = [];

    $recordingTool = new class($calls) extends Tool
    {
        public function __construct(private array &$calls) {}

        public function handle(Request $request): Response
        {
            $this->calls[] = $request->get('value');

            return Response::text((string) $request->get('value'));
        }

        public function schema(JsonSchema $schema): array
        {
            return ['value' => $schema->string()->required()];
        }
    };

    $context = toolSearchContext([
        SayHiTool::class,
        $recordingTool,
    ]);
    $executeTools = toolFromContext($context, 'execute_tools');

    $result = callContextTool($context, $executeTools, [
        'calls' => [
            ['name' => 'say-hi-tool', 'arguments' => []],
            ['name' => $recordingTool->name(), 'arguments' => ['value' => 'not-run']],
        ],
    ]);

    expect($result['isError'])->toBeTrue()
        ->and($result['payload']['ok'])->toBeFalse()
        ->and($result['payload']['results'])->toHaveCount(1)
        ->and($result['payload']['results'][0]['isError'])->toBeTrue()
        ->and($calls)->toBe([]);
});

it('forwards nested tool notifications', function (): void {
    $context = toolSearchContext([StreamingTool::class]);
    $executeTools = toolFromContext($context, 'execute_tools');
    $responses = callContextToolResponses($context, $executeTools, [
        'calls' => [[
            'name' => 'streaming-tool',
            'arguments' => ['count' => 2],
        ]],
    ]);

    expect($responses)->toHaveCount(3)
        ->and($responses[0]['method'])->toBe('stream/progress')
        ->and($responses[1]['method'])->toBe('stream/progress')
        ->and(json_decode($responses[2]['result']['content'][0]['text'], true)['ok'])->toBeTrue();
});

it('enforces the configured call and output limits', function (): void {
    config()->set('mcp.tool_search.max_tool_calls', 1);
    config()->set('mcp.tool_search.max_output_bytes', 256);

    $context = toolSearchContext([SayHiTool::class]);
    $executeTools = toolFromContext($context, 'execute_tools');

    $callLimit = callContextTool($context, $executeTools, [
        'calls' => [
            ['name' => 'say-hi-tool', 'arguments' => ['name' => 'One']],
            ['name' => 'say-hi-tool', 'arguments' => ['name' => 'Two']],
        ],
    ]);

    $outputLimit = callContextTool($context, $executeTools, [
        'calls' => [[
            'name' => 'say-hi-tool',
            'arguments' => ['name' => str_repeat('x', 256)],
        ]],
    ]);

    expect($callLimit['isError'])->toBeTrue()
        ->and($callLimit['content'][0]['text'])->toContain('The calls field must not have more than 1 items.')
        ->and($outputLimit['payload'])->toBe([
            'ok' => false,
            'error' => [
                'kind' => 'OutputLimitExceeded',
                'message' => 'The tool output exceeded 256 bytes.',
            ],
            'completedToolCalls' => 1,
            'attemptedToolCalls' => 1,
        ]);
});

it('expands into two tools and rejects duplicate catalog names', function (): void {
    $context = toolSearchContext([
        SayHiTool::class,
        new SayHiTool,
    ]);
    $searchTools = toolFromContext($context, 'search_tools');
    $executeTools = toolFromContext($context, 'execute_tools');

    $result = callContextTool($context, $searchTools);

    expect([$searchTools->name(), $executeTools->name()])->toBe(['search_tools', 'execute_tools'])
        ->and($result['isError'])->toBeTrue()
        ->and($result['content'][0]['text'])->toBe('Duplicate tool name [say-hi-tool] in ToolsSearch catalog.');
});

it('rejects generated tool name collisions', function (): void {
    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [
            ToolsSearch::for([SayHiTool::class]),
            new class extends Tool
            {
                protected string $name = 'search_tools';

                public function handle(): Response
                {
                    return Response::text('collision');
                }
            },
        ],
        resources: [],
        prompts: [],
    );

    expect(fn (): Collection => $context->tools())
        ->toThrow(InvalidArgumentException::class, 'Duplicate server tool name [search_tools].');
});

function toolSearchContext(array $tools): ServerContext
{
    return new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        implementation: new Implementation('Test Server', '1.0.0'),
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [ToolsSearch::for($tools)],
        resources: [],
        prompts: [],
    );
}

function toolFromContext(ServerContext $context, string $name): Tool
{
    $tool = $context->tools()->first(fn (Tool $tool): bool => $tool->name() === $name);

    if (! $tool instanceof Tool) {
        throw new LogicException("Tool [{$name}] was not registered.");
    }

    return $tool;
}

function callContextTool(ServerContext $context, Tool $tool, array $arguments = []): array
{
    $responses = callContextToolResponses($context, $tool, $arguments);
    $result = $responses[array_key_last($responses)]['result'];

    if (isset($result['content'][0]['text'])) {
        $payload = json_decode($result['content'][0]['text'], true);

        if (is_array($payload)) {
            $result['payload'] = $payload;
        }
    }

    return $result;
}

function callContextToolResponses(ServerContext $context, Tool $tool, array $arguments = []): array
{
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => $tool->name(),
            'arguments' => $arguments,
        ],
    ]);

    app()->instance('mcp.request', $request->toRequest());
    $response = (new CallTool)->handle($request, $context);
    $responses = $response instanceof JsonRpcResponse ? [$response] : iterator_to_array($response);

    return array_map(fn (JsonRpcResponse $response): array => $response->toArray(), $responses);
}
