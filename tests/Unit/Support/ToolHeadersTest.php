<?php

declare(strict_types=1);

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Exceptions\ClientException;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Support\ToolHeaders;

function schemaWith(array $properties): array
{
    return ['type' => 'object', 'properties' => $properties];
}

it('accepts a valid annotation', function (): void {
    expect(ToolHeaders::invalid(schemaWith([
        'region' => ['type' => 'string', 'x-mcp-header' => 'Region'],
        'query' => ['type' => 'string'],
    ])))->toBeNull();
});

it('accepts an annotation on a nested property', function (): void {
    expect(ToolHeaders::invalid(schemaWith([
        'target' => ['type' => 'object', 'properties' => [
            'region' => ['type' => 'string', 'x-mcp-header' => 'Region'],
        ]],
    ])))->toBeNull();
});

it('rejects an invalid annotation', function (array $properties, string $reason): void {
    expect(ToolHeaders::invalid(schemaWith($properties)))->toContain($reason);
})->with([
    'empty' => [['a' => ['type' => 'string', 'x-mcp-header' => '']], 'not a valid header name token'],
    'space' => [['a' => ['type' => 'string', 'x-mcp-header' => 'My Header']], 'not a valid header name token'],
    'newline' => [['a' => ['type' => 'string', 'x-mcp-header' => "Bad\nName"]], 'not a valid header name token'],
    'not a string' => [['a' => ['type' => 'string', 'x-mcp-header' => 12]], 'not a valid header name token'],
    'number type' => [['a' => ['type' => 'number', 'x-mcp-header' => 'A']], 'must sit on a string, integer, or boolean'],
    'array type' => [['a' => ['type' => 'array', 'x-mcp-header' => 'A']], 'must sit on a string, integer, or boolean'],
    'duplicate' => [[
        'a' => ['type' => 'string', 'x-mcp-header' => 'Region'],
        'b' => ['type' => 'string', 'x-mcp-header' => 'region'],
    ], 'is used more than once'],
]);

it('rejects an annotation that is not statically reachable', function (): void {
    expect(ToolHeaders::invalid([
        'type' => 'object',
        'properties' => [
            'rows' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => ['region' => ['type' => 'string', 'x-mcp-header' => 'Region']],
            ]],
        ],
    ]))->toContain('outside the statically reachable properties');
});

it('extracts and encodes the annotated values', function (): void {
    $schema = schemaWith([
        'region' => ['type' => 'string', 'x-mcp-header' => 'Region'],
        'limit' => ['type' => 'integer', 'x-mcp-header' => 'Limit'],
        'dry' => ['type' => 'boolean', 'x-mcp-header' => 'Dry'],
        'label' => ['type' => 'string', 'x-mcp-header' => 'Label'],
        'query' => ['type' => 'string'],
    ]);

    expect(ToolHeaders::extract($schema, [
        'region' => 'us-west1',
        'limit' => 42,
        'dry' => false,
        'label' => 'Hello, 世界',
        'query' => 'select 1',
    ]))->toBe([
        'Mcp-Param-Region' => 'us-west1',
        'Mcp-Param-Limit' => '42',
        'Mcp-Param-Dry' => 'false',
        'Mcp-Param-Label' => '=?base64?'.base64_encode('Hello, 世界').'?=',
    ]);
});

it('omits a header when the argument is absent or null', function (): void {
    $schema = schemaWith([
        'region' => ['type' => 'string', 'x-mcp-header' => 'Region'],
        'zone' => ['type' => 'string', 'x-mcp-header' => 'Zone'],
    ]);

    expect(ToolHeaders::extract($schema, ['zone' => null]))->toBe([]);
});

it('extracts a nested value at its exact path', function (): void {
    $schema = schemaWith([
        'target' => ['type' => 'object', 'properties' => [
            'region' => ['type' => 'string', 'x-mcp-header' => 'Region'],
        ]],
    ]);

    expect(ToolHeaders::extract($schema, ['target' => ['region' => 'eu-west1']]))
        ->toBe(['Mcp-Param-Region' => 'eu-west1']);
});

it('mirrors a declared tool argument into the schema annotation', function (): void {
    $tool = new class extends Tool
    {
        protected string $name = 'execute-sql';

        protected string $description = 'Runs SQL';

        protected array $parameterHeaders = ['region' => 'Region'];

        public function schema(JsonSchema $schema): array
        {
            return [
                'region' => $schema->string()->required(),
                'query' => $schema->string()->required(),
            ];
        }

        public function handle(): Response
        {
            return Response::text('ok');
        }
    };

    $schema = $tool->toArray()['inputSchema'];

    expect($schema['properties']['region']['x-mcp-header'])->toBe('Region');
    expect($schema['properties']['query'])->not->toHaveKey('x-mcp-header');
    expect(ToolHeaders::invalid($schema))->toBeNull();
});

it('refuses to mirror an argument the schema does not define', function (): void {
    $tool = new class extends Tool
    {
        protected string $name = 'execute-sql';

        protected string $description = 'Runs SQL';

        protected array $parameterHeaders = ['nope' => 'Nope'];

        public function handle(): Response
        {
            return Response::text('ok');
        }
    };

    expect(fn (): array => $tool->toArray())
        ->toThrow(InvalidArgumentException::class, 'mirrors the unknown argument [nope]');
});

it('refuses to mirror an argument into an invalid header name', function (): void {
    $tool = new class extends Tool
    {
        protected string $name = 'execute-sql';

        protected string $description = 'Runs SQL';

        protected array $parameterHeaders = ['region' => 'Bad Header'];

        public function schema(JsonSchema $schema): array
        {
            return ['region' => $schema->string()->required()];
        }

        public function handle(): Response
        {
            return Response::text('ok');
        }
    };

    expect(fn (): array => $tool->toArray())
        ->toThrow(InvalidArgumentException::class, 'not a valid header name token');
});

it('rejects a header name with a trailing newline', function (): void {
    expect(ToolHeaders::invalid(schemaWith([
        'region' => ['type' => 'string', 'x-mcp-header' => "Region\n"],
    ])))->toContain('is not a valid header name token');
});

it('allows an argument literally named after the annotation', function (): void {
    expect(ToolHeaders::invalid(schemaWith([
        'x-mcp-header' => ['type' => 'string'],
        'region' => ['type' => 'string', 'x-mcp-header' => 'Region'],
    ])))->toBeNull();
});

it('rejects an annotation hiding inside a keyword the client cannot reach', function (): void {
    expect(ToolHeaders::invalid(schemaWith([
        'region' => [
            'type' => 'string',
            'x-mcp-header' => 'Region',
            'default' => ['x-mcp-header' => 'Sneaky'],
        ],
    ])))->toContain('outside the statically reachable properties');
});

it('refuses to mirror a value it cannot encode', function (): void {
    expect(fn (): array => ToolHeaders::extract(schemaWith([
        'region' => ['type' => 'string', 'x-mcp-header' => 'Region'],
    ]), ['region' => 1.5]))
        ->toThrow(ClientException::class, 'cannot be mirrored into a header');
});
