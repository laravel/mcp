<?php

declare(strict_types=1);

namespace Tests\Conformance\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class JsonSchema202012Tool extends Tool
{
    protected string $name = 'json_schema_2020_12_tool';

    protected string $description = 'Tool with JSON Schema 2020-12 features';

    public function handle(Request $request): Response
    {
        return Response::text('ok');
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'inputSchema' => [
                '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                'type' => 'object',
                '$defs' => [
                    'address' => [
                        '$anchor' => 'addressDef',
                        'type' => 'object',
                        'properties' => [
                            'street' => ['type' => 'string'],
                            'city' => ['type' => 'string'],
                        ],
                    ],
                ],
                'properties' => [
                    'name' => ['type' => 'string'],
                    'address' => ['$ref' => '#/$defs/address'],
                    'contactMethod' => ['type' => 'string', 'enum' => ['phone', 'email']],
                    'phone' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                ],
                'allOf' => [
                    ['anyOf' => [['required' => ['phone']], ['required' => ['email']]]],
                ],
                'if' => [
                    'properties' => ['contactMethod' => ['const' => 'phone']],
                    'required' => ['contactMethod'],
                ],
                'then' => ['required' => ['phone']],
                'else' => ['required' => ['email']],
                'additionalProperties' => false,
            ],
        ];
    }
}
