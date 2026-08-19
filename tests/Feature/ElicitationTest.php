<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Enums\ElicitationAction;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Exceptions\ElicitationNotSupportedException;
use Laravel\Mcp\Exceptions\InputRequiredException;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Exceptions\RootsNotSupportedException;
use Laravel\Mcp\Exceptions\SamplingNotSupportedException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Elicitations\ElicitResponse;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Transport\JsonRpcRequest;

class ElicitationServer extends Server
{
    protected array $tools = [
        ElicitationTool::class,
        StreamingElicitationTool::class,
        MultiRoundElicitationTool::class,
        SimultaneousInputsTool::class,
        RememberingTool::class,
        SamplingTool::class,
        RootsTool::class,
    ];

    protected array $prompts = [ElicitationPrompt::class];

    protected array $resources = [ElicitationResource::class];
}

class ElicitationTool extends Tool
{
    public function handle(Request $request): Response
    {
        $response = $request->ask('Your GitHub username', fn (JsonSchema $schema): array => [
            'name' => $schema->string()->required(),
        ]);

        if ($response->declined()) {
            return Response::error('Declined');
        }

        if ($response->cancelled()) {
            return Response::error('Cancelled');
        }

        return Response::text("Hi {$response['name']}");
    }
}

class StreamingElicitationTool extends Tool
{
    public function handle(Request $request): Generator
    {
        $response = $request->ask('Choose a release', [
            'type' => 'object',
            'properties' => ['release' => ['type' => 'string']],
            'required' => ['release'],
        ]);

        yield Response::text("Release {$response['release']}");
    }
}

class MultiRoundElicitationTool extends Tool
{
    public function handle(Request $request): Response
    {
        $schema = fn (JsonSchema $schema): array => ['value' => $schema->string()->required()];

        $first = $request->ask('First value', $schema, 'first');
        $second = $request->ask('Second value', $schema, 'second');

        return Response::text("{$first['value']} then {$second['value']}");
    }
}

class ElicitationPrompt extends Prompt
{
    public function handle(Request $request): Response
    {
        $response = $request->ask('Pick a topic', [
            'type' => 'object',
            'properties' => ['topic' => ['type' => 'string']],
        ]);

        return Response::text("Explain {$response['topic']}");
    }
}

class ElicitationResource extends Resource
{
    public function handle(Request $request): Response
    {
        $response = $request->ask('Pick a locale', [
            'type' => 'object',
            'properties' => ['locale' => ['type' => 'string']],
        ]);

        return Response::text("Locale {$response['locale']}");
    }
}

function elicitationMeta(array $elicitation = ['form' => []]): array
{
    return [
        MetaKey::CLIENT_CAPABILITIES->value => ['elicitation' => $elicitation],
    ];
}

it('elicits and accepts form input', function (): void {
    ElicitationServer::tool(ElicitationTool::class)
        ->assertInputRequired()
        ->assertElicits('Your GitHub username')
        ->respond(['name' => 'octocat'])
        ->assertSee('Hi octocat')
        ->assertOk();
});

it('exposes declined and cancelled form input', function (string $action, string $message): void {
    ElicitationServer::tool(ElicitationTool::class)
        ->respond([], $action)
        ->assertHasErrors([$message]);
})->with([
    ['decline', 'Declined'],
    ['cancel', 'Cancelled'],
]);

it('keeps earlier answers across elicitation rounds', function (): void {
    ElicitationServer::tool(MultiRoundElicitationTool::class)
        ->assertElicits('First value')
        ->respond(['value' => 'one'])
        ->assertElicits('Second value')
        ->respond(['value' => 'two'])
        ->assertSee('one then two');
});

it('round trips and integrity checks the request state', function (): void {
    $request = fn (string $name, string $requestState): JsonRpcRequest => JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => $name,
            'arguments' => [],
            'requestState' => $requestState,
        ],
    ]);

    $issued = (new JsonRpcRequest(id: 1, method: 'tools/call', params: ['name' => 'multi-round-elicitation-tool']))
        ->encodeRequestState(['first' => ['action' => 'accept']]);

    expect($request('multi-round-elicitation-tool', $issued)->toRequest()->inputResponses())
        ->toBe(['first' => ['action' => 'accept']]);

    expect(fn (): Request => $request('multi-round-elicitation-tool', 'tampered')->toRequest())
        ->toThrow(JsonRpcException::class, 'failed integrity verification');

    expect(fn (): Request => $request('elicitation-tool', $issued)->toRequest())
        ->toThrow(JsonRpcException::class, 'issued for a different request');

    Carbon::setTestNow(Carbon::now()->addHours(2));

    expect(fn (): Request => $request('multi-round-elicitation-tool', $issued)->toRequest())
        ->toThrow(JsonRpcException::class, 'has expired');

    Carbon::setTestNow();
});

it('supports elicitation from generators', function (): void {
    ElicitationServer::tool(StreamingElicitationTool::class)
        ->assertInputRequired()
        ->respond(['release' => 'v2.0.0'])
        ->assertSee('Release v2.0.0');
});

it('supports elicitation from prompts and resources', function (): void {
    ElicitationServer::prompt(ElicitationPrompt::class)
        ->assertInputRequired()
        ->respond(['topic' => 'MRTR'])
        ->assertSee('Explain MRTR');

    ElicitationServer::resource(ElicitationResource::class)
        ->assertInputRequired()
        ->respond(['locale' => 'en'])
        ->assertSee('Locale en');
});

it('keys default elicitations per call and stably across replays', function (): void {
    $schema = fn (JsonSchema $schema): array => ['name' => $schema->string()->required()];
    $keyFor = function (Request $request, ?string $key = null) use ($schema): string {
        try {
            $request->ask('Name', $schema, $key);
        } catch (InputRequiredException $inputRequiredException) {
            return (string) array_key_first($inputRequiredException->inputRequests());
        }

        throw new RuntimeException('The request did not require input.');
    };

    $shared = new Request(meta: elicitationMeta());

    expect($keyFor(new Request(meta: elicitationMeta())))
        ->toBe($keyFor(new Request(meta: elicitationMeta())))
        ->and($keyFor($shared))->not->toBe($keyFor($shared))
        ->and($keyFor(new Request(meta: elicitationMeta()), 'name'))->toBe('name');
});

it('matches numeric elicitation keys the client echoes back', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'elicitation-tool',
            'arguments' => [],
            '_meta' => elicitationMeta(),
            'requestState' => (new JsonRpcRequest(id: 1, method: 'tools/call', params: ['name' => 'elicitation-tool']))
                ->encodeRequestState(['7' => ['action' => 'accept', 'content' => ['value' => 'kept']]]),
            'inputResponses' => json_decode('{"9":{"action":"accept","content":{"value":"fresh"}}}', true),
        ],
    ])->toRequest();

    expect($request->inputResponses())->toHaveKeys(['7', '9'])
        ->and($request->ask('Pick', ['type' => 'object'], '9')->get('value'))->toBe('fresh')
        ->and($request->ask('Pick', ['type' => 'object'], '7')->get('value'))->toBe('kept');
});

it('reports boolean client capabilities as declared', function (): void {
    $request = new Request(meta: [
        MetaKey::CLIENT_CAPABILITIES->value => ['roots' => ['listChanged' => true], 'sampling' => ['enabled' => false]],
    ]);

    expect($request->clientSupports('roots.listChanged'))->toBeTrue()
        ->and($request->clientSupports('sampling.enabled'))->toBeFalse()
        ->and($request->clientSupports('roots'))->toBeTrue();
});

it('gates form elicitation by client capability', function (): void {
    $legacy = new Request(meta: elicitationMeta([]));
    $unsupported = new Request;

    expect($legacy->clientSupports('elicitation'))->toBeTrue()
        ->and($legacy->canAsk())->toBeTrue()
        ->and($unsupported->canAsk())->toBeFalse()
        ->and(fn (): ElicitResponse => $unsupported->ask('Your GitHub username', ['type' => 'object']))
        ->toThrow(ElicitationNotSupportedException::class, 'The client does not support form elicitation.');
});

it('wraps input responses and validates accepted content', function (): void {
    $response = ElicitResponse::from([
        'action' => 'accept',
        'content' => ['email' => 'octocat@example.com'],
    ]);

    expect($response->action())->toBe(ElicitationAction::Accept)
        ->and($response->accepted())->toBeTrue()
        ->and($response->declined())->toBeFalse()
        ->and($response->cancelled())->toBeFalse()
        ->and($response->get('email'))->toBe('octocat@example.com')
        ->and($response['email'])->toBe('octocat@example.com')
        ->and($response->validate(['email' => 'required|email']))->toBe([
            'email' => 'octocat@example.com',
        ]);

    expect(fn (): array => $response->validate(['email' => 'required|url']))
        ->toThrow(ValidationException::class);

    $declined = ElicitResponse::from(['action' => 'decline']);

    expect(fn (): mixed => $declined->get('email'))
        ->toThrow(LogicException::class, 'The elicitation was not accepted.')
        ->and(fn (): array => $declined->validate(['email' => 'required']))
        ->toThrow(LogicException::class, 'The elicitation was not accepted.')
        ->and(isset($declined['email']))->toBeFalse();

    expect(function () use ($response): void {
        $response['email'] = 'other@example.com';
    })->toThrow(LogicException::class, 'Elicitation responses are immutable.')
        ->and(function () use ($response): void {
            unset($response['email']);
        })->toThrow(LogicException::class, 'Elicitation responses are immutable.');
});

class SimultaneousInputsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $responses = $request->inputResponses();

        if (! isset($responses['first'], $responses['second'])) {
            throw new InputRequiredException([
                'first' => ['method' => 'elicitation/create', 'params' => ['mode' => 'form', 'message' => 'First']],
                'second' => ['method' => 'elicitation/create', 'params' => ['mode' => 'form', 'message' => 'Second']],
            ], $responses);
        }

        return Response::text("{$responses['first']['content']['value']} and {$responses['second']['content']['value']}");
    }
}

it('answers each simultaneous input request in turn', function (): void {
    ElicitationServer::tool(SimultaneousInputsTool::class)
        ->assertInputRequired()
        ->respond(['value' => 'one'], key: 'first')
        ->assertInputRequired()
        ->respond(['value' => 'two'], key: 'second')
        ->assertSee('one and two');
});

it('rejects an input response that is not an object', function (): void {
    $request = new Request(meta: elicitationMeta(), inputResponses: ['picked' => 'nope']);

    expect(fn (): ElicitResponse => $request->ask('Pick', ['type' => 'object'], 'picked'))
        ->toThrow(JsonRpcException::class, 'Invalid params: The [inputResponses.picked] member must be an object.');
});

it('rejects a malformed input response at the json rpc boundary', function (): void {
    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'elicitation-tool',
            'arguments' => [],
            'inputResponses' => ['picked' => 'nope'],
        ],
    ]);

    expect(fn (): Request => $request->toRequest())
        ->toThrow(JsonRpcException::class, 'Invalid params: The [inputResponses.picked] member must be an object.');
});

it('treats an unrecognised elicitation action as cancelled', function (): void {
    $response = ElicitResponse::from(['action' => 'maybe', 'content' => ['name' => 'octocat']]);

    expect($response->cancelled())->toBeTrue()
        ->and($response->accepted())->toBeFalse()
        ->and($response->content())->toBe([]);
});

it('serializes an empty requested schema properties as an object', function (): void {
    $request = new Request(meta: elicitationMeta());

    $inputRequests = [];

    try {
        $request->ask('Pick', ['type' => 'object', 'properties' => []]);
    } catch (InputRequiredException $inputRequiredException) {
        $inputRequests = $inputRequiredException->inputRequests();
    }

    expect($inputRequests)->toHaveCount(1)
        ->and(json_encode($inputRequests[array_key_first($inputRequests)]['params']['requestedSchema']))
        ->toContain('"properties":{}');
});

class RememberingTool extends Tool
{
    public static int $sideEffects = 0;

    public function handle(Request $request): Response
    {
        $order = $request->remember('order', function (): string {
            static::$sideEffects++;

            return 'order-'.static::$sideEffects;
        });

        $confirm = $request->ask('Confirm the order', fn (JsonSchema $schema): array => [
            'ok' => $schema->boolean()->required(),
        ], 'confirm');

        return Response::text("{$order} confirmed: ".($confirm['ok'] ? 'yes' : 'no'));
    }
}

class SamplingTool extends Tool
{
    public function handle(Request $request): Response
    {
        $completion = $request->sample([
            ['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Say hello.']],
        ], 100, ['systemPrompt' => 'Be terse.'], 'completion');

        return Response::text('Model said: '.$completion['content']['text']);
    }
}

class RootsTool extends Tool
{
    public function handle(Request $request): Response
    {
        return Response::text('Roots: '.implode(', ', array_column($request->roots('roots'), 'uri')));
    }
}

it('runs a side effect once across elicitation rounds', function (): void {
    RememberingTool::$sideEffects = 0;

    ElicitationServer::tool(RememberingTool::class)
        ->assertInputRequired()
        ->respond(['ok' => true])
        ->assertSee('order-1 confirmed: yes');

    expect(RememberingTool::$sideEffects)->toBe(1);
});

it('seals handler state into the request state', function (): void {
    $request = new JsonRpcRequest(id: 1, method: 'tools/call', params: ['name' => 'remembering-tool']);

    $decoded = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => [
            'name' => 'remembering-tool',
            'requestState' => $request->encodeRequestState([], ['order' => 'order-1']),
        ],
    ])->toRequest();

    expect($decoded->state())->toBe(['order' => 'order-1'])
        ->and($decoded->remember('order', fn (): string => 'never-called'))->toBe('order-1');
});

it('binds the request state to the original arguments', function (): void {
    $issued = (new JsonRpcRequest(id: 1, method: 'tools/call', params: [
        'name' => 'elicitation-tool',
        'arguments' => ['amount' => 1],
    ]))->encodeRequestState([]);

    $replay = fn (array $arguments): Request => JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => ['name' => 'elicitation-tool', 'arguments' => $arguments, 'requestState' => $issued],
    ])->toRequest();

    expect($replay(['amount' => 1])->state())->toBe([])
        ->and($replay(['amount' => 1_000_000]))->toThrow(JsonRpcException::class);
})->throws(JsonRpcException::class, 'issued for a different request');

it('ignores argument key order when binding the request state', function (): void {
    $issued = (new JsonRpcRequest(id: 1, method: 'tools/call', params: [
        'name' => 'elicitation-tool',
        'arguments' => ['a' => 1, 'b' => 2],
    ]))->encodeRequestState(['first' => ['action' => 'accept']]);

    $request = JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => ['name' => 'elicitation-tool', 'arguments' => ['b' => 2, 'a' => 1], 'requestState' => $issued],
    ])->toRequest();

    expect($request->inputResponses())->toBe(['first' => ['action' => 'accept']]);
});

it('requests a sampling turn', function (): void {
    ElicitationServer::tool(SamplingTool::class)
        ->assertInputRequired()
        ->respondWith([
            'role' => 'assistant',
            'content' => ['type' => 'text', 'text' => 'Hello.'],
            'model' => 'claude-sonnet-5',
            'stopReason' => 'endTurn',
        ], 'completion')
        ->assertSee('Model said: Hello.');
});

it('requests the client roots', function (): void {
    ElicitationServer::tool(RootsTool::class)
        ->assertInputRequired()
        ->respondWith(['roots' => [['uri' => 'file:///app', 'name' => 'app']]], 'roots')
        ->assertSee('Roots: file:///app');
});

it('gates sampling and roots by client capability', function (): void {
    $bare = new Request;

    expect(fn (): array => $bare->sample([], 10))
        ->toThrow(SamplingNotSupportedException::class, 'The client does not support sampling.')
        ->and(fn (): array => $bare->roots())
        ->toThrow(RootsNotSupportedException::class, 'The client does not support roots.');
});

it('rejects form schemas the specification does not allow', function (array $properties, string $message): void {
    $request = new Request(meta: elicitationMeta());

    expect(fn (): ElicitResponse => $request->ask('Pick', ['type' => 'object', 'properties' => $properties]))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'nested object' => [['profile' => ['type' => 'object']], 'The [profile] property must be a primitive.'],
    'free array' => [['tags' => ['type' => 'array', 'items' => ['type' => 'string']]], 'The [tags] property must be an enum array.'],
]);

it('allows enum arrays in form schemas', function (): void {
    $request = new Request(meta: elicitationMeta());

    expect(fn (): ElicitResponse => $request->ask('Pick', [
        'type' => 'object',
        'properties' => ['tags' => ['type' => 'array', 'items' => ['enum' => ['a', 'b']]]],
    ]))->toThrow(InputRequiredException::class);
});

it('returns the given default when the elicitation was not accepted', function (): void {
    $declined = ElicitResponse::from(['action' => 'decline']);

    expect($declined->get('name', 'anonymous'))->toBe('anonymous')
        ->and($declined->content())->toBe([])
        ->and(fn (): mixed => $declined->get('name'))->toThrow(LogicException::class);
});

it('declines and cancels through the test helpers', function (): void {
    ElicitationServer::tool(ElicitationTool::class)->decline()->assertHasErrors(['Declined']);
    ElicitationServer::tool(ElicitationTool::class)->cancel()->assertHasErrors(['Cancelled']);
});
