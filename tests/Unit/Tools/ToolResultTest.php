<?php

use Laravel\Mcp\Response;

it('returns a valid tool result', function (): void {
    $responseText = 'This is a test response.';
    $response = Response::text($responseText);

    $expectedArray = [
        'type' => 'text',
        'text' => $responseText,
    ];

    expect($response->content()->toArray())->toBe($expectedArray);
});

it('returns a valid error tool result', function (): void {
    $responseText = 'This is a test error response.';
    $response = Response::error($responseText);

    $expectedArray = [
        'type' => 'text',
        'text' => $responseText,
    ];

    expect($response->content()->toArray())->toBe($expectedArray);
});
