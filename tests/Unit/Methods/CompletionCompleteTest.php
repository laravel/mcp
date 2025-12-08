<?php

use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Completions\CompletionResponse;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Contracts\SupportsCompletion;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\CompletionComplete;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Support\UriTemplate;

class CompletionMethodTestServer extends Server
{
    protected string $name = 'Completion Method Test Server';

    protected array $capabilities = [
        'completions' => [],
    ];

    protected array $prompts = [
        CompletionMethodTestPrompt::class,
    ];

    protected array $resources = [
        CompletionMethodTestResource::class,
    ];
}

class CompletionMethodTestPrompt extends Prompt implements SupportsCompletion
{
    protected string $description = 'Test prompt';

    public function arguments(): array
    {
        return [new Argument('test', 'Test arg')];
    }

    public function complete(string $argument, string $value, array $context): CompletionResponse
    {
        return CompletionResponse::from(['test']);
    }

    public function handle(\Laravel\Mcp\Request $request): Response
    {
        return Response::text('test');
    }
}

class CompletionMethodTestResource extends Resource implements SupportsCompletion
{
    protected string $uri = 'file://test';

    protected string $mimeType = 'text/plain';

    public function description(): string
    {
        return 'Test resource';
    }

    public function complete(string $argument, string $value, array $context): CompletionResponse
    {
        return CompletionResponse::from(['resource-test']);
    }

    public function handle(\Laravel\Mcp\Request $request): Response
    {
        return Response::text('test');
    }
}

class NonCompletionPrompt extends Prompt
{
    protected string $description = 'Non-completion prompt';

    public function handle(\Laravel\Mcp\Request $request): Response
    {
        return Response::text('test');
    }
}

class CompletionMethodTestResourceWithTemplate extends Resource implements HasUriTemplate, SupportsCompletion
{
    protected string $mimeType = 'text/plain';

    protected string $description = 'Test resource template';

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('file://users/{userId}');
    }

    public function complete(string $argument, string $value, array $context): CompletionResponse
    {
        return CompletionResponse::from(['template-test']);
    }

    public function handle(\Laravel\Mcp\Request $request): Response
    {
        return Response::text('test');
    }
}

it('throws an exception when completion capability is not declared', function (): void {
    $server = new class(new FakeTransporter) extends Server
    {
        protected string $name = 'Test';

        protected array $prompts = [CompletionMethodTestPrompt::class];
    };

    $method = new CompletionComplete;
    $request = new JsonRpcRequest('1', 'completion/complete', [
        'ref' => ['type' => 'ref/prompt', 'name' => 'completion-method-test-prompt'],
        'argument' => ['name' => 'test', 'value' => ''],
    ]);

    $context = $server->createContext();

    $method->handle($request, $context);
})->throws(JsonRpcException::class, 'Server does not support completions capability');

it('throws an exception when the ref is missing', function (): void {
    $method = new CompletionComplete;
    $request = new JsonRpcRequest('1', 'completion/complete', [
        'argument' => ['name' => 'test', 'value' => ''],
    ]);

    $server = new CompletionMethodTestServer(new FakeTransporter);
    $context = $server->createContext();

    $method->handle($request, $context);
})->throws(JsonRpcException::class, 'Missing required parameters: ref and argument');

it('throws an exception when an argument is missing', function (): void {
    $method = new CompletionComplete;
    $request = new JsonRpcRequest('1', 'completion/complete', [
        'ref' => ['type' => 'ref/prompt', 'name' => 'test'],
    ]);

    $server = new CompletionMethodTestServer(new FakeTransporter);
    $context = $server->createContext();

    $method->handle($request, $context);
})->throws(JsonRpcException::class, 'Missing required parameters: ref and argument');

it('throws an exception when the argument name is missing', function (): void {
    $method = new CompletionComplete;
    $request = new JsonRpcRequest('1', 'completion/complete', [
        'ref' => ['type' => 'ref/prompt', 'name' => 'completion-method-test-prompt'],
        'argument' => ['value' => 'test'],
    ]);

    $server = new CompletionMethodTestServer(new FakeTransporter);
    $context = $server->createContext();

    $method->handle($request, $context);
})->throws(JsonRpcException::class, 'Missing argument name');

it('throws exception for invalid reference type', function (): void {
    $method = new CompletionComplete;
    $request = new JsonRpcRequest('1', 'completion/complete', [
        'ref' => ['type' => 'invalid/type', 'name' => 'test'],
        'argument' => ['name' => 'test', 'value' => ''],
    ]);

    $server = new CompletionMethodTestServer(new FakeTransporter);
    $context = $server->createContext();

    $method->handle($request, $context);
})->throws(JsonRpcException::class, 'Invalid reference type');

it('throws exception when prompt name is missing', function (): void {
    $method = new CompletionComplete;
    $request = new JsonRpcRequest('1', 'completion/complete', [
        'ref' => ['type' => 'ref/prompt'],
        'argument' => ['name' => 'test', 'value' => ''],
    ]);

    $server = new CompletionMethodTestServer(new FakeTransporter);
    $context = $server->createContext();

    $method->handle($request, $context);
})->throws(JsonRpcException::class, 'Missing [name] parameter');

it('throws exception when prompt not found', function (): void {
    $method = new CompletionComplete;
    $request = new JsonRpcRequest('1', 'completion/complete', [
        'ref' => ['type' => 'ref/prompt', 'name' => 'non-existent'],
        'argument' => ['name' => 'test', 'value' => ''],
    ]);

    $server = new CompletionMethodTestServer(new FakeTransporter);
    $context = $server->createContext();

    $method->handle($request, $context);
})->throws(JsonRpcException::class, 'Prompt [non-existent] not found');

it('throws exception when resource URI is missing', function (): void {
    $method = new CompletionComplete;
    $request = new JsonRpcRequest('1', 'completion/complete', [
        'ref' => ['type' => 'ref/resource'],
        'argument' => ['name' => 'test', 'value' => ''],
    ]);

    $server = new CompletionMethodTestServer(new FakeTransporter);
    $context = $server->createContext();

    $method->handle($request, $context);
})->throws(JsonRpcException::class, 'Missing [uri] parameter');

it('throws exception when resource not found', function (): void {
    $method = new CompletionComplete;
    $request = new JsonRpcRequest('1', 'completion/complete', [
        'ref' => ['type' => 'ref/resource', 'uri' => 'file://non-existent'],
        'argument' => ['name' => 'test', 'value' => ''],
    ]);

    $server = new CompletionMethodTestServer(new FakeTransporter);
    $context = $server->createContext();

    $method->handle($request, $context);
})->throws(JsonRpcException::class, 'Resource [file://non-existent] not found');

it('throws exception when primitive does not support completion', function (): void {
    $server = new class(new FakeTransporter) extends Server
    {
        protected string $name = 'Test';

        protected array $capabilities = ['completions' => []];

        protected array $prompts = [NonCompletionPrompt::class];
    };

    $method = new CompletionComplete;
    $request = new JsonRpcRequest('1', 'completion/complete', [
        'ref' => ['type' => 'ref/prompt', 'name' => 'non-completion-prompt'],
        'argument' => ['name' => 'test', 'value' => ''],
    ]);

    $context = $server->createContext();

    $method->handle($request, $context);
})->throws(JsonRpcException::class, 'does not support completion');

it('completes for prompt', function (): void {
    $method = new CompletionComplete;
    $request = new JsonRpcRequest('1', 'completion/complete', [
        'ref' => ['type' => 'ref/prompt', 'name' => 'completion-method-test-prompt'],
        'argument' => ['name' => 'test', 'value' => ''],
    ]);

    $server = new CompletionMethodTestServer(new FakeTransporter);
    $context = $server->createContext();

    $response = $method->handle($request, $context);

    expect($response->toArray())
        ->toHaveKey('result')
        ->and($response->toArray()['result'])
        ->toHaveKey('completion')
        ->and($response->toArray()['result']['completion']['values'])
        ->toBe(['test']);
});

it('completes for resource', function (): void {
    $method = new CompletionComplete;
    $request = new JsonRpcRequest('1', 'completion/complete', [
        'ref' => ['type' => 'ref/resource', 'uri' => 'file://test'],
        'argument' => ['name' => 'test', 'value' => ''],
    ]);

    $server = new CompletionMethodTestServer(new FakeTransporter);
    $context = $server->createContext();

    $response = $method->handle($request, $context);

    expect($response->toArray())
        ->toHaveKey('result')
        ->and($response->toArray()['result'])
        ->toHaveKey('completion')
        ->and($response->toArray()['result']['completion']['values'])
        ->toBe(['resource-test']);
});

it('finds resource by template match', function (): void {
    $server = new class(new FakeTransporter) extends Server
    {
        protected string $name = 'Test';

        protected array $capabilities = ['completions' => []];

        protected array $resources = [CompletionMethodTestResourceWithTemplate::class];
    };

    $method = new CompletionComplete;
    $request = new JsonRpcRequest('1', 'completion/complete', [
        'ref' => ['type' => 'ref/resource', 'uri' => 'file://users/123'],
        'argument' => ['name' => 'test', 'value' => ''],
    ]);

    $context = $server->createContext();

    $response = $method->handle($request, $context);

    expect($response->toArray())
        ->toHaveKey('result')
        ->and($response->toArray()['result']['completion']['values'])
        ->toBe(['template-test']);
});

it('finds resource by exact template string', function (): void {
    $server = new class(new FakeTransporter) extends Server
    {
        protected string $name = 'Test';

        protected array $capabilities = ['completions' => []];

        protected array $resources = [CompletionMethodTestResourceWithTemplate::class];
    };

    $method = new CompletionComplete;
    $request = new JsonRpcRequest('1', 'completion/complete', [
        'ref' => ['type' => 'ref/resource', 'uri' => 'file://users/{userId}'],
        'argument' => ['name' => 'test', 'value' => ''],
    ]);

    $context = $server->createContext();

    $response = $method->handle($request, $context);

    expect($response->toArray())
        ->toHaveKey('result')
        ->and($response->toArray()['result']['completion']['values'])
        ->toBe(['template-test']);
});

it('extracts and passes context arguments to completion method', function (): void {
    $method = new CompletionComplete;
    $request = new JsonRpcRequest('1', 'completion/complete', [
        'ref' => ['type' => 'ref/prompt', 'name' => 'completion-method-test-prompt'],
        'argument' => ['name' => 'test', 'value' => ''],
        'context' => [
            'arguments' => [
                'arg1' => 'test-value',
                'arg2' => 'another-value',
            ],
        ],
    ]);

    $server = new CompletionMethodTestServer(new FakeTransporter);
    $context = $server->createContext();

    $response = $method->handle($request, $context);

    expect($response->toArray())
        ->toHaveKey('result')
        ->and($response->toArray()['result']['completion']['values'])
        ->toBe(['test']);
});

it('passes empty context when context is not provided', function (): void {
    $method = new CompletionComplete;
    $request = new JsonRpcRequest('1', 'completion/complete', [
        'ref' => ['type' => 'ref/prompt', 'name' => 'completion-method-test-prompt'],
        'argument' => ['name' => 'test', 'value' => ''],
    ]);

    $server = new CompletionMethodTestServer(new FakeTransporter);
    $context = $server->createContext();

    $response = $method->handle($request, $context);

    expect($response->toArray())
        ->toHaveKey('result')
        ->and($response->toArray()['result']['completion']['values'])
        ->toBe(['test']);
});
