<?php

declare(strict_types=1);

namespace Tests\Conformance\Tools;

use Laravel\Mcp\Exceptions\InputRequiredException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class MultipleInputsRequiredTool extends Tool
{
    protected string $name = 'test_input_required_result_multiple_inputs';

    protected string $description = 'Tests requesting several kinds of input in a single result';

    public function handle(Request $request): Response
    {
        $responses = $request->inputResponses();

        if (! isset($responses['user_name'], $responses['completion'], $responses['roots'])) {
            throw new InputRequiredException([
                'user_name' => [
                    'method' => 'elicitation/create',
                    'params' => [
                        'mode' => 'form',
                        'message' => 'What is your name?',
                        'requestedSchema' => [
                            'type' => 'object',
                            'properties' => ['name' => ['type' => 'string']],
                            'required' => ['name'],
                        ],
                    ],
                ],
                'completion' => [
                    'method' => 'sampling/createMessage',
                    'params' => [
                        'messages' => [
                            ['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Say hello.']],
                        ],
                        'maxTokens' => 100,
                    ],
                ],
                'roots' => [
                    'method' => 'roots/list',
                    'params' => [],
                ],
            ], $responses);
        }

        return Response::text('All inputs received.');
    }
}
