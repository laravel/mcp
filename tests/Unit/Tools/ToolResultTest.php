<?php

use Laravel\Mcp\Server\Tools\TextContent;
use Laravel\Mcp\Server\Tools\ToolResult;

it('returns a valid tool result', function () {
    $responseText = 'This is a test response.';
    $response = ToolResult::text($responseText);

    $expectedArray = [
        'content' => [
            [
                'type' => 'text',
                'text' => $responseText,
            ],
        ],
        'isError' => false,
    ];

    expect($response->toArray())->toBe($expectedArray);
});

it('returns a valid error tool result', function () {
    $responseText = 'This is a test error response.';
    $response = ToolResult::error($responseText);

    $expectedArray = [
        'content' => [
            [
                'type' => 'text',
                'text' => $responseText,
            ],
        ],
        'isError' => true,
    ];

    expect($response->toArray())->toBe($expectedArray);
});

it('can handle multiple content items', function () {
    $plainText = 'This is the plain text version.';
    $markdown = 'This is the **markdown** version.';

    $response = ToolResult::items(
        new TextContent($plainText),
        new TextContent($markdown)
    );

    $expectedArray = [
        'content' => [
            [
                'type' => 'text',
                'text' => $plainText,
            ],
            [
                'type' => 'text',
                'text' => $markdown,
            ],
        ],
        'isError' => false,
    ];

    expect($response->toArray())->toBe($expectedArray);
});
