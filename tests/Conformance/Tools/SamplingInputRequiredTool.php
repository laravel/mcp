<?php

declare(strict_types=1);

namespace Tests\Conformance\Tools;

use Laravel\Mcp\Exceptions\InputRequiredException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class SamplingInputRequiredTool extends Tool
{
    protected string $name = 'test_input_required_result_sampling';

    protected string $description = 'Tests requesting a sampling turn through an input required result';

    public function handle(Request $request): Response
    {
        $responses = $request->inputResponses();

        if (! isset($responses['completion'])) {
            throw new InputRequiredException([
                'completion' => [
                    'method' => 'sampling/createMessage',
                    'params' => [
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => ['type' => 'text', 'text' => 'Say hello.'],
                            ],
                        ],
                        'maxTokens' => 100,
                    ],
                ],
            ], $responses);
        }

        return Response::text('Sampling complete.');
    }
}
