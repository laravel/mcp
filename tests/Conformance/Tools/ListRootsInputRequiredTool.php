<?php

declare(strict_types=1);

namespace Tests\Conformance\Tools;

use Laravel\Mcp\Exceptions\InputRequiredException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListRootsInputRequiredTool extends Tool
{
    protected string $name = 'test_input_required_result_list_roots';

    protected string $description = 'Tests requesting the client roots through an input required result';

    public function handle(Request $request): Response
    {
        $responses = $request->inputResponses();

        if (! isset($responses['roots'])) {
            throw new InputRequiredException([
                'roots' => [
                    'method' => 'roots/list',
                    'params' => [],
                ],
            ], $responses);
        }

        return Response::text('Roots received.');
    }
}
