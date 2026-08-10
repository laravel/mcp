<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Client;
use Laravel\Mcp\Enums\ProtocolVersion;
use Tests\Fixtures\Client\FakeTransport;

function toolsListResponse(int $id, array $tools): string
{
    return (string) json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'result' => ['resultType' => 'complete', 'tools' => $tools],
    ]);
}

function annotatedTool(string $header = 'Region'): array
{
    return [
        'name' => 'execute_sql',
        'description' => 'Runs SQL',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'region' => ['type' => 'string', 'x-mcp-header' => $header],
                'query' => ['type' => 'string'],
            ],
        ],
    ];
}

it('mirrors annotated parameters into headers on a tool call', function (): void {
    $transport = new FakeTransport;
    $transport->negotiates = true;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = toolsListResponse(2, [annotatedTool()]);
    $transport->responses[] = toolCallResponse(3, 'done');

    $client = (new Client($transport))->withProtocolVersion(ProtocolVersion::LATEST);
    $client->tools();
    $client->callTool('execute_sql', ['region' => 'us-west1', 'query' => 'select 1']);

    expect($transport->sentHeaders[2])->toBe(['Mcp-Param-Region' => 'us-west1']);
});

it('sends no parameter headers for a tool it has not listed', function (): void {
    $transport = new FakeTransport;
    $transport->negotiates = true;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = toolCallResponse(2, 'done');

    (new Client($transport))->withProtocolVersion(ProtocolVersion::LATEST)->callTool('execute_sql', ['region' => 'us-west1']);

    expect($transport->sentHeaders[1])->toBe([]);
});

it('excludes a tool whose annotation is invalid from the tool list', function (): void {
    Log::spy();

    $transport = new FakeTransport;
    $transport->negotiates = true;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = toolsListResponse(2, [
        annotatedTool('Bad Header'),
        ['name' => 'plain', 'description' => 'Fine', 'inputSchema' => ['type' => 'object']],
    ]);

    $tools = (new Client($transport))->withProtocolVersion(ProtocolVersion::LATEST)->tools();

    expect($tools->keys()->all())->toBe(['plain']);

    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message): bool => str_contains($message, 'execute_sql') && str_contains($message, 'not a valid header name token'),
    );
});

it('mirrors parameters from the tool primitive without a listing round trip', function (): void {
    $transport = new FakeTransport;
    $transport->negotiates = true;
    $transport->responses[] = discoverResponse();
    $transport->responses[] = toolsListResponse(2, [annotatedTool()]);
    $transport->responses[] = toolCallResponse(3, 'done');

    $client = (new Client($transport))->withProtocolVersion(ProtocolVersion::LATEST);
    $tool = $client->tools()->get('execute_sql');

    (fn (): array => $this->toolSchemas = [])->call($client);

    $tool->call(['region' => 'us-west1', 'query' => 'select 1']);

    expect($transport->sentHeaders[2])->toBe(['Mcp-Param-Region' => 'us-west1']);
});
