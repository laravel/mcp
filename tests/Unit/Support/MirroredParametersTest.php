<?php

declare(strict_types=1);

use Laravel\Mcp\Exceptions\MirroredParameterException;
use Laravel\Mcp\Support\MirroredParameters;
use Laravel\Mcp\Support\MirroredParameterType;
use Laravel\Mcp\Support\SchemaPath;
use Pest\Expectation;

function schemaWith(array $properties): array
{
    return ['type' => 'object', 'properties' => $properties];
}

it('parses a valid annotation', function (): void {
    $parameters = MirroredParameters::fromSchema(schemaWith([
        'region' => ['type' => 'string', 'x-mcp-header' => 'Region'],
        'query' => ['type' => 'string'],
    ]));

    expect($parameters->all())
        ->toHaveCount(1)
        ->sequence(function (Expectation $parameter): void {
            $parameter
                ->header()->toBe('Mcp-Param-Region')
                ->name->toBe('Region')
                ->type->toBe(MirroredParameterType::String)
                ->path->segments->toBe(['region']);
        });
});

it('parses a nested annotation', function (): void {
    $parameters = MirroredParameters::fromSchema(schemaWith([
        'target' => ['type' => 'object', 'properties' => [
            'region' => ['type' => 'string', 'x-mcp-header' => 'Region'],
        ]],
    ]));

    expect((string) $parameters->all()->first()->path)->toBe('/target/region')
        ->and($parameters->headers(['target' => ['region' => 'us-west1']]))
        ->toBe(['Mcp-Param-Region' => 'us-west1']);
});

it('rejects invalid annotations', function (array $properties, string $reason): void {
    expect(fn (): MirroredParameters => MirroredParameters::fromSchema(schemaWith($properties)))
        ->toThrow(MirroredParameterException::class, $reason);
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

it('rejects annotations that are not statically reachable', function (array $schema): void {
    expect(fn (): MirroredParameters => MirroredParameters::fromSchema($schema))
        ->toThrow(MirroredParameterException::class, 'statically reachable');
})->with([
    'items' => [schemaWith([
        'rows' => ['type' => 'array', 'items' => [
            'type' => 'object',
            'properties' => ['region' => ['type' => 'string', 'x-mcp-header' => 'Region']],
        ]],
    ])],
    'oneOf' => [schemaWith([
        'target' => ['oneOf' => [
            ['type' => 'object', 'properties' => ['region' => ['type' => 'string', 'x-mcp-header' => 'Region']]],
        ]],
    ])],
    'root' => [['type' => 'object', 'x-mcp-header' => 'Region', 'properties' => []]],
]);

it('allows a property literally named like the annotation', function (): void {
    $parameters = MirroredParameters::fromSchema(schemaWith([
        'x-mcp-header' => ['type' => 'string'],
    ]));

    expect($parameters)->toHaveCount(0);
});

it('encodes every mirrored type', function (): void {
    $parameters = MirroredParameters::fromSchema(schemaWith([
        'region' => ['type' => 'string', 'x-mcp-header' => 'Region'],
        'limit' => ['type' => 'integer', 'x-mcp-header' => 'Limit'],
        'dry' => ['type' => 'boolean', 'x-mcp-header' => 'Dry'],
        'label' => ['type' => 'string', 'x-mcp-header' => 'Label'],
        'absent' => ['type' => 'string', 'x-mcp-header' => 'Absent'],
        'null' => ['type' => 'string', 'x-mcp-header' => 'Null'],
    ]));

    expect($parameters->headers([
        'region' => 'us-west1',
        'limit' => 42,
        'dry' => false,
        'label' => 'Hello, 世界',
        'null' => null,
    ]))
        ->toBe([
            'Mcp-Param-Region' => 'us-west1',
            'Mcp-Param-Limit' => '42',
            'Mcp-Param-Dry' => 'false',
            'Mcp-Param-Label' => '=?base64?SGVsbG8sIOS4lueVjA==?=',
        ])
        ->toHaveKeys(['Mcp-Param-Region', 'Mcp-Param-Limit', 'Mcp-Param-Dry', 'Mcp-Param-Label'])
        ->not->toHaveKey('Mcp-Param-Absent')
        ->not->toHaveKey('Mcp-Param-Null')
        ->each->toBeString();
});

it('rejects an integer beyond the safe range', function (): void {
    $parameters = MirroredParameters::fromSchema(schemaWith([
        'limit' => ['type' => 'integer', 'x-mcp-header' => 'Limit'],
    ]));

    expect(fn (): array => $parameters->headers(['limit' => 9007199254740992]))
        ->toThrow(MirroredParameterException::class, 'cannot be mirrored');
});

it('mirrors a property whose name contains a dot', function (): void {
    $parameters = MirroredParameters::fromSchema(schemaWith([
        'a.b' => ['type' => 'string', 'x-mcp-header' => 'Dotted'],
        'a' => ['type' => 'object', 'properties' => [
            'b' => ['type' => 'string', 'x-mcp-header' => 'Nested'],
        ]],
    ]));

    expect($parameters->headers(['a.b' => 'right', 'a' => ['b' => 'nested']]))->toBe([
        'Mcp-Param-Dotted' => 'right',
        'Mcp-Param-Nested' => 'nested',
    ]);
});

it('renders paths as json pointers', function (): void {
    expect((string) SchemaPath::root()->child('a.b')->child('c'))->toBe('/a.b/c');
});
