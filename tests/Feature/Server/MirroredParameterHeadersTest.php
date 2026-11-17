<?php

declare(strict_types=1);

use Tests\Fixtures\MirroredHeaderServer;
use Tests\Fixtures\MirroredHeaderTool;

it('accepts mirrored parameter headers that match the body', function (array $arguments, array $headers): void {
    $message = mirroredHeaderToolMessage($arguments);

    $response = $this->postJson('test-mcp-mirrored-headers', $message, [...mcpHeaders($message), ...$headers]);

    $response->assertOk()->assertJsonPath('result.content.0.text', "Region: {$arguments['region']}");
})->with([
    'plain value' => [['region' => 'us-west1'], ['Mcp-Param-Region' => 'us-west1']],
    'base64 value' => [['region' => 'Hello, 世界'], ['Mcp-Param-Region' => '=?base64?SGVsbG8sIOS4lueVjA==?=']],
    'value without the base64 suffix is literal' => [['region' => '=?base64?SGVsbG8='], ['Mcp-Param-Region' => '=?base64?SGVsbG8=']],
    'lowercase header name and whitespace' => [['region' => 'us-west1'], ['mcp-param-region' => "  us-west1\t"]],
    'integer value' => [['region' => 'us-west1', 'replicas' => 3], ['Mcp-Param-Region' => 'us-west1', 'Mcp-Param-Replicas' => '3']],
    'integer with a decimal point' => [['region' => 'us-west1', 'replicas' => 3], ['Mcp-Param-Region' => 'us-west1', 'Mcp-Param-Replicas' => '3.0']],
    'integer body sent as a whole float' => [['region' => 'us-west1', 'replicas' => 3.0], ['Mcp-Param-Region' => 'us-west1', 'Mcp-Param-Replicas' => '3']],
    'null body value without a header' => [['region' => 'us-west1', 'replicas' => null], ['Mcp-Param-Region' => 'us-west1']],
]);

it('rejects mirrored parameter headers that are missing, malformed, or contradict the body', function (array $arguments, array $headers, string $error): void {
    $message = mirroredHeaderToolMessage($arguments);

    $response = $this->postJson('test-mcp-mirrored-headers', $message, [...mcpHeaders($message), ...$headers]);

    $response->assertStatus(400);

    expect($response->json())->toEqual([
        'jsonrpc' => '2.0',
        'id' => 7,
        'error' => ['code' => -32020, 'message' => "Header mismatch: {$error}"],
    ]);
})->with([
    'missing header' => [['region' => 'us-west1'], [], 'The [Mcp-Param-Region] header is required.'],
    'invalid base64 padding' => [['region' => 'Hello'], ['Mcp-Param-Region' => '=?base64?SGVsbG8?='], 'The [Mcp-Param-Region] header value does not match the request body value at [/region].'],
    'invalid base64 characters' => [['region' => 'Hello'], ['Mcp-Param-Region' => '=?base64?SGVs!!!bG8=?='], 'The [Mcp-Param-Region] header value does not match the request body value at [/region].'],
    'base64 with whitespace' => [['region' => 'Hello'], ['Mcp-Param-Region' => '=?base64?S G V s bG8=?='], 'The [Mcp-Param-Region] header value does not match the request body value at [/region].'],
    'raw non-ascii value' => [['region' => 'Hello, 世界'], ['Mcp-Param-Region' => 'Hello, 世界'], 'The [Mcp-Param-Region] header value contains invalid characters.'],
    'different value' => [['region' => 'us-west1'], ['Mcp-Param-Region' => 'eu-west1'], 'The [Mcp-Param-Region] header value does not match the request body value at [/region].'],
    'different integer' => [['region' => 'us-west1', 'replicas' => 3], ['Mcp-Param-Region' => 'us-west1', 'Mcp-Param-Replicas' => '3.5'], 'The [Mcp-Param-Replicas] header value does not match the request body value at [/replicas].'],
    'non-numeric integer header' => [['region' => 'us-west1', 'replicas' => 3], ['Mcp-Param-Region' => 'us-west1', 'Mcp-Param-Replicas' => 'three'], 'The [Mcp-Param-Replicas] header value does not match the request body value at [/replicas].'],
    'header without a body value' => [['region' => 'us-west1'], ['Mcp-Param-Region' => 'us-west1', 'Mcp-Param-Replicas' => '3'], 'The [Mcp-Param-Replicas] header is present but the request body has no [/replicas] argument.'],
    'body value of the wrong type' => [['region' => 'us-west1', 'replicas' => 'three'], ['Mcp-Param-Region' => 'us-west1', 'Mcp-Param-Replicas' => 'three'], 'The [/replicas] argument is not a valid [integer].'],
]);

it('does not expect mirrored parameter headers outside of HTTP', function (): void {
    MirroredHeaderServer::tool(MirroredHeaderTool::class, ['region' => 'us-west1'])
        ->assertOk()
        ->assertSee('Region: us-west1');
});
