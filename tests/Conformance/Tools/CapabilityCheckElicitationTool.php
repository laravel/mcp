<?php

declare(strict_types=1);

namespace Tests\Conformance\Tools;

use Laravel\Mcp\Exceptions\InputRequiredException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CapabilityCheckElicitationTool extends Tool
{
    protected string $name = 'test_input_required_result_capabilities';

    protected string $description = 'Tests that input is only requested for capabilities the client declares';

    public function handle(Request $request): Response
    {
        $responses = $request->inputResponses();
        $inputRequests = [];

        if ($request->canAsk() && ! isset($responses['user_name'])) {
            $inputRequests['user_name'] = [
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
            ];
        }

        if ($request->clientSupports('sampling') && ! isset($responses['completion'])) {
            $inputRequests['completion'] = [
                'method' => 'sampling/createMessage',
                'params' => [
                    'messages' => [
                        ['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Say hello.']],
                    ],
                    'maxTokens' => 100,
                ],
            ];
        }

        if ($inputRequests !== []) {
            throw new InputRequiredException($inputRequests, $responses);
        }

        return Response::text('Capabilities respected.');
    }
}
