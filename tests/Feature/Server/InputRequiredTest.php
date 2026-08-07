<?php

declare(strict_types=1);

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\InputRequired;
use Laravel\Mcp\Server\Tool;
use Tests\Fixtures\ArrayTransport;

function elicitingServer(): Server
{
    $tool = new class extends Tool
    {
        protected string $name = 'ask-name';

        protected string $description = 'Asks for a name.';

        public function handle(Request $request): Response|InputRequired
        {
            if ($request->inputResponses() === []) {
                return InputRequired::make([
                    'who' => [
                        'method' => 'elicitation/create',
                        'params' => [
                            'mode' => 'form',
                            'message' => 'Who are you?',
                            'requestedSchema' => [
                                'type' => 'object',
                                'properties' => ['name' => ['type' => 'string']],
                                'required' => ['name'],
                            ],
                        ],
                    ],
                ], requestState: 'state-for-'.$request->get('greeting'));
            }

            return Response::text(sprintf(
                '%s, %s (%s)',
                $request->get('greeting'),
                $request->inputResponses()['who']['content']['name'],
                $request->requestState(),
            ));
        }
    };

    return new class(new ArrayTransport, $tool) extends Server
    {
        public function __construct(ArrayTransport $transport, Tool $tool)
        {
            parent::__construct($transport);

            $this->tools = [$tool];
        }
    };
}

function askName(array $params = [], ?array $capabilities = ['elicitation' => []]): array
{
    $server = elicitingServer();
    $transport = (fn (): ArrayTransport => $this->transport)->call($server);

    $server->start();

    ($transport->handler)((string) json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => 'ask-name',
            'arguments' => ['greeting' => 'Hello'],
            '_meta' => [
                ...protocolMeta(),
                'io.modelcontextprotocol/clientCapabilities' => $capabilities,
            ],
            ...$params,
        ],
    ]));

    return json_decode((string) $transport->sent[0], true);
}

it('returns an input required result', function (): void {
    $response = askName();

    expect($response['result']['resultType'])->toBe('input_required');
    expect($response['result']['inputRequests']['who']['method'])->toBe('elicitation/create');
    expect($response['result']['requestState'])->toBe('state-for-Hello');
    expect($response['result'])->not->toHaveKey('content');
});

it('completes the request when the input responses come back', function (): void {
    $response = askName([
        'inputResponses' => ['who' => ['action' => 'accept', 'content' => ['name' => 'John']]],
        'requestState' => 'state-for-Hello',
    ]);

    expect($response['result']['resultType'])->toBe('complete');
    expect($response['result']['content'][0]['text'])->toBe('Hello, John (state-for-Hello)');
});

it('rejects input requests the client has no capability for', function (): void {
    $response = askName(capabilities: []);

    expect($response['error']['code'])->toBe(-32021);
    expect($response['error']['message'])->toBe('The client did not declare the required [elicitation] capability.');
    expect($response['error']['data'])->toBe(['capability' => 'elicitation']);
});

it('refuses an input required result with nothing in it', function (): void {
    expect(fn (): InputRequired => InputRequired::make())
        ->toThrow(InvalidArgumentException::class, 'must carry input requests, request state, or both');
});

it('refuses an input request the protocol does not define', function (): void {
    expect(fn (): InputRequired => InputRequired::make(['x' => ['method' => 'tools/call']]))
        ->toThrow(InvalidArgumentException::class, 'Input request [x] must use one of');
});
