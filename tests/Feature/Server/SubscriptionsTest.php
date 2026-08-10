<?php

declare(strict_types=1);

use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Subscription;
use Tests\Fixtures\ArrayTransport;
use Tests\Fixtures\ExampleServer;

function listenMessages(array $notifications, ?Closure $factory = null): array
{
    $transport = new ArrayTransport;
    $server = ($factory ?? pushingServer([]))($transport);

    $server->start();

    ($transport->handler)((string) json_encode([
        'jsonrpc' => '2.0',
        'id' => 7,
        'method' => 'subscriptions/listen',
        'params' => [
            '_meta' => protocolMeta(),
            'notifications' => $notifications,
        ],
    ]));

    return array_map(fn (string $sent): array => json_decode($sent, true), $transport->sent);
}

function pushingServer(array $notifications): Closure
{
    return fn (ArrayTransport $transport): Server => new class($transport, $notifications) extends ExampleServer
    {
        /**
         * @param  array<int, Response>  $pushed
         */
        public function __construct(ArrayTransport $transport, protected array $pushed = [])
        {
            parent::__construct($transport);
        }

        protected function boot(): void
        {
            $this->addCapability('tools.listChanged');
            $this->addCapability('prompts.listChanged');
            $this->addCapability('resources.listChanged');
            $this->addCapability('resources.subscribe');
        }

        protected function subscriptions(Subscription $subscription): iterable
        {
            return $this->pushed;
        }
    };
}

it('acknowledges the subscription first and carries the subscription id', function (): void {
    $messages = listenMessages(['toolsListChanged' => true]);

    expect($messages[0]['method'])->toBe('notifications/subscriptions/acknowledged');
    expect($messages[0]['params']['_meta']['io.modelcontextprotocol/subscriptionId'])->toBe(7);
    expect($messages[0]['params']['notifications'])->toBe(['toolsListChanged' => true]);
});

it('closes the subscription gracefully with an empty result', function (): void {
    $messages = listenMessages(['toolsListChanged' => true]);
    $last = end($messages);

    expect($last['id'])->toBe(7);
    expect($last['result']['resultType'])->toBe('complete');
    expect($last['result']['_meta']['io.modelcontextprotocol/subscriptionId'])->toBe(7);
});

it('acknowledges only the notifications it was asked for', function (): void {
    $messages = listenMessages([
        'toolsListChanged' => true,
        'promptsListChanged' => false,
        'resourceSubscriptions' => ['file://resources/last-log-line-resource'],
    ]);

    expect($messages[0]['params']['notifications'])->toBe([
        'toolsListChanged' => true,
        'resourceSubscriptions' => ['file://resources/last-log-line-resource'],
    ]);
});

it('acknowledges nothing when the filter is empty', function (): void {
    expect(listenMessages([])[0]['params']['notifications'])->toBe([]);
});

it('delivers a subscribed notification stamped with the subscription id', function (): void {
    $messages = listenMessages(
        ['toolsListChanged' => true],
        pushingServer([Response::notification('notifications/tools/list_changed')]),
    );

    expect($messages[1]['method'])->toBe('notifications/tools/list_changed');
    expect($messages[1]['params']['_meta']['io.modelcontextprotocol/subscriptionId'])->toBe(7);
});

it('withholds a notification the client did not subscribe to', function (): void {
    $messages = listenMessages(
        ['toolsListChanged' => true],
        pushingServer([Response::notification('notifications/prompts/list_changed')]),
    );

    expect($messages)->toHaveCount(2);
    expect($messages[1])->toHaveKey('result');
});

it('delivers a resource update only for a subscribed uri', function (): void {
    $factory = pushingServer([
        Response::notification('notifications/resources/updated', ['uri' => 'file://watched']),
        Response::notification('notifications/resources/updated', ['uri' => 'file://ignored']),
    ]);

    $messages = listenMessages(['resourceSubscriptions' => ['file://watched']], $factory);

    expect($messages)->toHaveCount(3);
    expect($messages[1]['params']['uri'])->toBe('file://watched');
});

it('leaves caching hints off the subscription response', function (): void {
    $messages = listenMessages(['toolsListChanged' => true]);
    $last = end($messages);

    expect($last['result'])->not->toHaveKey('ttlMs');
    expect($last['result'])->not->toHaveKey('cacheScope');
});

it('refuses a notification the server does not advertise', function (): void {
    $messages = listenMessages(
        ['toolsListChanged' => true, 'resourceSubscriptions' => ['file://a']],
        fn (ArrayTransport $transport): Server => new ExampleServer($transport),
    );

    expect($messages[0]['params']['notifications'])->toBe([]);
});

it('acknowledges an empty filter as a json object', function (): void {
    $transport = new ArrayTransport;
    $server = new ExampleServer($transport);

    $server->start();

    ($transport->handler)((string) json_encode([
        'jsonrpc' => '2.0',
        'id' => 7,
        'method' => 'subscriptions/listen',
        'params' => ['_meta' => protocolMeta(), 'notifications' => ['toolsListChanged' => true]],
    ]));

    expect((string) $transport->sent[0])->toContain('"notifications":{}');
});

it('keeps the server subscription id when a notification carries its own meta', function (): void {
    $messages = listenMessages(
        ['toolsListChanged' => true],
        pushingServer([
            Response::notification('notifications/tools/list_changed', [
                '_meta' => [
                    'io.modelcontextprotocol/subscriptionId' => 99,
                    'app/trace' => 'abc',
                ],
            ]),
        ]),
    );

    expect($messages[1]['params']['_meta']['io.modelcontextprotocol/subscriptionId'])->toBe(7);
    expect($messages[1]['params']['_meta']['app/trace'])->toBe('abc');
});
