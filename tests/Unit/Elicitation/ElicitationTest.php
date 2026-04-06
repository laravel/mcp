<?php

use Laravel\Mcp\Server\Elicitation\Elicitation;
use Laravel\Mcp\Server\Elicitation\ElicitSchema;
use Laravel\Mcp\Server\Elicitation\UrlElicitationRequiredException;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Transport\FakeTransporter;

it('sends a form elicitation and returns accepted result', function (): void {
    $transport = new FakeTransporter;
    $transport->expectElicitation(['action' => 'accept', 'content' => ['name' => 'Taylor']]);

    $elicitation = new Elicitation($transport, ['elicitation' => ['form' => []]]);

    $result = $elicitation->form('What is your name?', fn (ElicitSchema $schema): array => [
        'name' => $schema->string('Your Name')->required(),
    ]);

    expect($result->accepted())->toBeTrue();
    expect($result->get('name'))->toBe('Taylor');

    $sent = $transport->sentElicitations();
    expect($sent)->toHaveCount(1);
    expect($sent[0]['method'])->toBe('elicitation/create');
    expect($sent[0]['params']['mode'])->toBe('form');
    expect($sent[0]['params']['message'])->toBe('What is your name?');
    expect($sent[0]['params']['requestedSchema']['type'])->toBe('object');
    expect($sent[0]['params']['requestedSchema']['properties']['name']['type'])->toBe('string');
    expect($sent[0]['params']['requestedSchema']['required'])->toBe(['name']);
});

it('sends a form elicitation with an array schema', function (): void {
    $transport = new FakeTransporter;
    $transport->expectElicitation(['action' => 'accept', 'content' => ['name' => 'Taylor']]);

    $elicitation = new Elicitation($transport, ['elicitation' => ['form' => []]]);

    $result = $elicitation->form('Provide details', [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
        ],
    ]);

    expect($result->accepted())->toBeTrue();

    $sent = $transport->sentElicitations();
    expect($sent[0]['params']['requestedSchema']['properties']['name']['type'])->toBe('string');
});

it('sends a url elicitation', function (): void {
    $transport = new FakeTransporter;
    $transport->expectElicitation(['action' => 'accept']);

    $elicitation = new Elicitation($transport, ['elicitation' => ['form' => [], 'url' => []]]);

    $result = $elicitation->url('Authorize GitHub', 'https://example.com/oauth');

    expect($result->accepted())->toBeTrue();
    expect($result->elicitationId())->not->toBeNull();

    $sent = $transport->sentElicitations();
    expect($sent[0]['params']['mode'])->toBe('url');
    expect($sent[0]['params']['url'])->toBe('https://example.com/oauth');
});

it('sends a url elicitation with custom elicitation id', function (): void {
    $transport = new FakeTransporter;
    $transport->expectElicitation(['action' => 'accept']);

    $elicitation = new Elicitation($transport, ['elicitation' => ['form' => [], 'url' => []]]);

    $result = $elicitation->url('Authorize', 'https://example.com', 'custom-id');

    expect($result->elicitationId())->toBe('custom-id');

    $sent = $transport->sentElicitations();
    expect($sent[0]['params']['elicitationId'])->toBe('custom-id');
});

it('sends a completion notification', function (): void {
    $transport = new FakeTransporter;

    $elicitation = new Elicitation($transport, ['elicitation' => ['form' => [], 'url' => []]]);
    $elicitation->notifyComplete('elicit-123');

    $sent = $transport->sentMessages();
    expect($sent)->toHaveCount(1);

    $decoded = json_decode($sent[0], true);
    expect($decoded['method'])->toBe('notifications/elicitation/complete');
    expect($decoded['params']['elicitationId'])->toBe('elicit-123');
});

it('handles declined response', function (): void {
    $transport = new FakeTransporter;
    $transport->expectElicitation(['action' => 'decline']);

    $elicitation = new Elicitation($transport, ['elicitation' => ['form' => []]]);

    $result = $elicitation->form('Name?', fn (ElicitSchema $s): array => [
        'name' => $s->string('Name'),
    ]);

    expect($result->declined())->toBeTrue();
    expect($result->accepted())->toBeFalse();
});

it('handles cancelled response', function (): void {
    $transport = new FakeTransporter;
    $transport->expectElicitation(['action' => 'cancel']);

    $elicitation = new Elicitation($transport, ['elicitation' => ['form' => []]]);

    $result = $elicitation->form('Name?', fn (ElicitSchema $s): array => [
        'name' => $s->string('Name'),
    ]);

    expect($result->cancelled())->toBeTrue();
});

it('throws when client does not support elicitation', function (): void {
    $transport = new FakeTransporter;
    $elicitation = new Elicitation($transport, []);

    $elicitation->form('Name?', fn (ElicitSchema $s): array => []);
})->throws(JsonRpcException::class, 'Client does not support elicitation. Ensure the MCP client declares elicitation support in its capabilities during initialization.');

it('throws when client does not support url mode', function (): void {
    $transport = new FakeTransporter;
    // Empty elicitation = form only
    $elicitation = new Elicitation($transport, ['elicitation' => []]);

    $elicitation->url('Authorize', 'https://example.com');
})->throws(JsonRpcException::class, 'Client does not support elicitation mode [url].');

it('allows form mode when elicitation is empty object', function (): void {
    $transport = new FakeTransporter;
    $transport->expectElicitation(['action' => 'accept', 'content' => ['name' => 'Taylor']]);

    // Empty array = empty JSON object = form only per spec
    $elicitation = new Elicitation($transport, ['elicitation' => []]);

    $result = $elicitation->form('Name?', fn (ElicitSchema $s): array => [
        'name' => $s->string('Name'),
    ]);

    expect($result->accepted())->toBeTrue();
});

it('allows form mode when explicitly declared', function (): void {
    $transport = new FakeTransporter;
    $transport->expectElicitation(['action' => 'accept']);

    $elicitation = new Elicitation($transport, ['elicitation' => ['form' => []]]);

    $result = $elicitation->form('Name?', fn (ElicitSchema $s): array => [
        'name' => $s->string('Name'),
    ]);

    expect($result->accepted())->toBeTrue();
});

it('throws on response id mismatch', function (): void {
    $transport = new class extends FakeTransporter
    {
        public function sendRequest(string $message): string
        {
            $this->sentElicitations[] = json_decode($message, true);

            return json_encode([
                'jsonrpc' => '2.0',
                'id' => 'wrong-id',
                'result' => ['action' => 'accept'],
            ]);
        }
    };

    $transport->setClientCapabilities(['elicitation' => ['form' => []]]);

    $elicitation = new Elicitation($transport, ['elicitation' => ['form' => []]]);

    $elicitation->form('Name?', fn (ElicitSchema $s): array => [
        'name' => $s->string('Name'),
    ]);
})->throws(JsonRpcException::class, 'Elicitation response id mismatch');

it('creates url elicitation required exception with correct code', function (): void {
    $exception = new UrlElicitationRequiredException('OAuth required', [
        ['mode' => 'url', 'url' => 'https://example.com/oauth'],
    ]);

    expect($exception->getCode())->toBe(-32042);
    expect($exception->getMessage())->toBe('OAuth required');

    $response = $exception->toJsonRpcResponse()->toArray();
    expect($response['error']['code'])->toBe(-32042);
    expect($response['error']['data']['elicitations'])->toBe([
        ['mode' => 'url', 'url' => 'https://example.com/oauth'],
    ]);
});
