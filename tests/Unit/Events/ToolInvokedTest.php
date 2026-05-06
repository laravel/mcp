<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Event;
use Laravel\Mcp\Events\InvokingTool;
use Laravel\Mcp\Events\ToolInvoked;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Tests\Fixtures\SayHiTool;
use Tests\Fixtures\SayHiTwiceTool;

it('dispatches InvokingTool and ToolInvoked on a successful tool call', function (): void {
    Event::fake([InvokingTool::class, ToolInvoked::class]);

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'say-hi-tool',
            'arguments' => ['name' => 'John Doe'],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [SayHiTool::class],
        resources: [],
        prompts: [],
    );

    $this->instance('mcp.request', $request->toRequest());
    (new CallTool)->handle($request, $context);

    Event::assertDispatched(InvokingTool::class, fn (InvokingTool $event): bool => $event->tool instanceof SayHiTool
        && $event->request instanceof Request
        && $event->request->get('name') === 'John Doe'
        && $event->invocationId !== '');

    Event::assertDispatched(ToolInvoked::class, fn (ToolInvoked $event): bool => $event->tool instanceof SayHiTool
        && $event->response instanceof Response
        && ! $event->response->isError()
        && $event->invocationId !== '');
});

it('uses the same invocationId for InvokingTool and ToolInvoked', function (): void {
    $invoking = null;
    $invoked = null;

    Event::listen(function (InvokingTool $event) use (&$invoking): void {
        $invoking = $event;
    });

    Event::listen(function (ToolInvoked $event) use (&$invoked): void {
        $invoked = $event;
    });

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'say-hi-tool',
            'arguments' => ['name' => 'John Doe'],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [SayHiTool::class],
        resources: [],
        prompts: [],
    );

    $this->instance('mcp.request', $request->toRequest());
    (new CallTool)->handle($request, $context);

    expect($invoking)->not->toBeNull()
        ->and($invoked)->not->toBeNull()
        ->and($invoking->invocationId)->toBe($invoked->invocationId);
});

it('dispatches both events when the tool returns multiple responses', function (): void {
    Event::fake([InvokingTool::class, ToolInvoked::class]);

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'say-hi-twice-tool',
            'arguments' => ['name' => 'John Doe'],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [SayHiTwiceTool::class],
        resources: [],
        prompts: [],
    );

    $this->instance('mcp.request', $request->toRequest());
    (new CallTool)->handle($request, $context);

    Event::assertDispatched(InvokingTool::class);
    Event::assertDispatched(ToolInvoked::class, fn (ToolInvoked $event): bool => is_array($event->response));
});

it('dispatches ToolInvoked with collected responses after a streamed tool finishes', function (): void {
    Event::fake([InvokingTool::class, ToolInvoked::class]);

    $tool = new class extends Tool
    {
        protected string $description = 'Streaming tool';

        public function handle(Request $request): Generator
        {
            yield Response::text('first');
            yield Response::text('second');
        }

        public function schema(JsonSchema $schema): array
        {
            return [];
        }
    };

    $toolClass = $tool::class;
    $this->instance($toolClass, $tool);

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => $tool->name(),
            'arguments' => [],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [$toolClass],
        resources: [],
        prompts: [],
    );

    $this->instance('mcp.request', $request->toRequest());
    $responses = (new CallTool)->handle($request, $context);
    iterator_to_array($responses);

    Event::assertDispatched(InvokingTool::class, 1);
    Event::assertDispatched(ToolInvoked::class, fn (ToolInvoked $event): bool => is_array($event->response)
        && count($event->response) === 2
        && $event->response[0] instanceof Response
        && $event->response[1] instanceof Response);
});

it('does not break the streamed response when a listener inspects ToolInvoked', function (): void {
    $tool = new class extends Tool
    {
        protected string $description = 'Streaming tool';

        public function handle(Request $request): Generator
        {
            yield Response::text('first');
            yield Response::text('second');
        }

        public function schema(JsonSchema $schema): array
        {
            return [];
        }
    };

    $toolClass = $tool::class;
    $this->instance($toolClass, $tool);

    $listenerSawResponses = null;
    Event::listen(function (ToolInvoked $event) use (&$listenerSawResponses): void {
        $listenerSawResponses = is_array($event->response) ? count($event->response) : null;
    });

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => $tool->name(),
            'arguments' => [],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [$toolClass],
        resources: [],
        prompts: [],
    );

    $this->instance('mcp.request', $request->toRequest());
    $responses = (new CallTool)->handle($request, $context);
    $envelopes = iterator_to_array($responses);

    expect($envelopes)->toHaveCount(1)
        ->and($listenerSawResponses)->toBe(2);

    $payload = $envelopes[0]->toArray();
    expect($payload['result']['content'])->toHaveCount(2)
        ->and($payload['result']['content'][0]['text'])->toBe('first')
        ->and($payload['result']['content'][1]['text'])->toBe('second');
});

it('dispatches both events when the tool returns a ResponseFactory', function (): void {
    Event::fake([InvokingTool::class, ToolInvoked::class]);

    $tool = new class extends Tool
    {
        protected string $description = 'Factory tool';

        public function handle(Request $request): ResponseFactory
        {
            return Response::make([Response::text('hello')]);
        }

        public function schema(JsonSchema $schema): array
        {
            return [];
        }
    };

    $toolClass = $tool::class;
    $this->instance($toolClass, $tool);

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => $tool->name(),
            'arguments' => [],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [$toolClass],
        resources: [],
        prompts: [],
    );

    $this->instance('mcp.request', $request->toRequest());
    (new CallTool)->handle($request, $context);

    Event::assertDispatched(ToolInvoked::class, fn (ToolInvoked $event): bool => $event->response instanceof ResponseFactory);
});

it('dispatches ToolInvoked when validation errors are converted to error responses', function (): void {
    Event::fake([InvokingTool::class, ToolInvoked::class]);

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'say-hi-tool',
            'arguments' => ['name' => ''],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [SayHiTool::class],
        resources: [],
        prompts: [],
    );

    $this->instance('mcp.request', $request->toRequest());
    (new CallTool)->handle($request, $context);

    Event::assertDispatched(InvokingTool::class);
    Event::assertDispatched(ToolInvoked::class, fn (ToolInvoked $event): bool => $event->response instanceof Response
        && $event->response->isError());
});

it('dispatches ToolInvoked when authentication errors are converted to error responses', function (): void {
    Event::fake([InvokingTool::class, ToolInvoked::class]);

    $tool = new class extends Tool
    {
        protected string $description = 'Unauthenticated tool';

        public function handle(Request $request): Response
        {
            throw new AuthenticationException('Unauthenticated.');
        }

        public function schema(JsonSchema $schema): array
        {
            return [];
        }
    };

    $toolClass = $tool::class;
    $this->instance($toolClass, $tool);

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => $tool->name(),
            'arguments' => [],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [$toolClass],
        resources: [],
        prompts: [],
    );

    $this->instance('mcp.request', $request->toRequest());
    (new CallTool)->handle($request, $context);

    Event::assertDispatched(ToolInvoked::class, fn (ToolInvoked $event): bool => $event->response instanceof Response
        && $event->response->isError());
});

it('dispatches InvokingTool but not ToolInvoked when the tool throws an uncaught exception', function (): void {
    Event::fake([InvokingTool::class, ToolInvoked::class]);

    $tool = new class extends Tool
    {
        protected string $description = 'Boom tool';

        public function handle(Request $request): Response
        {
            throw new RuntimeException('boom');
        }

        public function schema(JsonSchema $schema): array
        {
            return [];
        }
    };

    $toolClass = $tool::class;
    $this->instance($toolClass, $tool);

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => $tool->name(),
            'arguments' => [],
        ],
    ]);

    $context = new ServerContext(
        supportedProtocolVersions: ['2025-03-26'],
        serverCapabilities: [],
        serverName: 'Test Server',
        serverVersion: '1.0.0',
        instructions: 'Test instructions',
        maxPaginationLength: 50,
        defaultPaginationLength: 10,
        tools: [$toolClass],
        resources: [],
        prompts: [],
    );

    $this->instance('mcp.request', $request->toRequest());

    expect(fn (): Generator|\Laravel\Mcp\Server\Transport\JsonRpcResponse => (new CallTool)->handle($request, $context))
        ->toThrow(RuntimeException::class, 'boom');

    Event::assertDispatched(InvokingTool::class);
    Event::assertNotDispatched(ToolInvoked::class);
});
