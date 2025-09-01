<?php

use Laravel\Mcp\Server\Tools\ToolInputSchema;

test('sets string type correctly', function () {
    $schema = new ToolInputSchema;

    $schema->string('name');

    expect($schema->toArray())->toBe([
        'type' => 'object',
        'properties' => [
            'name' => ['type' => ToolInputSchema::TYPE_STRING],
        ],
    ]);
});

test('sets integer type correctly', function () {
    $schema = new ToolInputSchema;

    $schema->integer('age');

    expect($schema->toArray())->toBe([
        'type' => 'object',
        'properties' => [
            'age' => ['type' => ToolInputSchema::TYPE_INTEGER],
        ],
    ]);
});

test('sets number type correctly', function () {
    $schema = new ToolInputSchema;

    $schema->number('price');

    expect($schema->toArray())->toBe([
        'type' => 'object',
        'properties' => [
            'price' => ['type' => ToolInputSchema::TYPE_NUMBER],
        ],
    ]);
});

test('sets boolean type correctly', function () {
    $schema = new ToolInputSchema;

    $schema->boolean('is_active');

    expect($schema->toArray())->toBe([
        'type' => 'object',
        'properties' => [
            'is_active' => ['type' => ToolInputSchema::TYPE_BOOLEAN],
        ],
    ]);
});

test('description mutates last property only', function () {
    $schema = new ToolInputSchema;
    $schema->string('name')->description('The name of the item.');
    $schema->integer('quantity')->description('The number of items.');

    expect($schema->toArray())->toBe([
        'type' => 'object',
        'properties' => [
            'name' => ['type' => ToolInputSchema::TYPE_STRING, 'description' => 'The name of the item.'],
            'quantity' => ['type' => ToolInputSchema::TYPE_INTEGER, 'description' => 'The number of items.'],
        ],
    ]);

    $schema->description('New description');

    expect($schema->toArray())->toBe([
        'type' => 'object',
        'properties' => [
            'name' => ['type' => ToolInputSchema::TYPE_STRING, 'description' => 'The name of the item.'],
            'quantity' => ['type' => ToolInputSchema::TYPE_INTEGER, 'description' => 'New description'],
        ],
    ]);
});

test('description without property does nothing', function () {
    $schema = new ToolInputSchema;

    $schema->description('This should not be added.');

    expect(json_encode($schema->toArray()))->toEqual('{"type":"object","properties":{}}');
});

test('required accumulates without duplicates', function () {
    $schema = new ToolInputSchema;

    $schema->string('name')->required();
    $schema->string('name')->required();
    // Duplicate
    $schema->integer('age')->required();

    expect($schema->toArray())->toBe([
        'type' => 'object',
        'properties' => [
            'name' => ['type' => ToolInputSchema::TYPE_STRING],
            'age' => ['type' => ToolInputSchema::TYPE_INTEGER],
        ],
        'required' => ['name', 'age'],
    ]);
});

test('to array omits required when no fields marked required', function () {
    $schema = new ToolInputSchema;

    $schema->string('name');
    $schema->integer('age');

    expect($schema->toArray())->toBe([
        'type' => 'object',
        'properties' => [
            'name' => ['type' => ToolInputSchema::TYPE_STRING],
            'age' => ['type' => ToolInputSchema::TYPE_INTEGER],
        ],
    ]);
});

it('can be used fluently', function () {
    $schema = new ToolInputSchema;

    $schema->string('name')->description('User name')->required();
    $schema->integer('level')->description('User level')->required();
    $schema->boolean('verified')->description('Is user verified');

    expect($schema->toArray())->toBe([
        'type' => 'object',
        'properties' => [
            'name' => ['type' => ToolInputSchema::TYPE_STRING, 'description' => 'User name'],
            'level' => ['type' => ToolInputSchema::TYPE_INTEGER, 'description' => 'User level'],
            'verified' => ['type' => ToolInputSchema::TYPE_BOOLEAN, 'description' => 'Is user verified'],
        ],
        'required' => ['name', 'level'],
    ]);
});

test('required without property does nothing', function () {
    $schema = new ToolInputSchema;

    $schema->required();

    expect(json_encode($schema->toArray()))->toEqual('{"type":"object","properties":{}}');
});

test('properties can be marked optional', function () {
    $schema = new ToolInputSchema;

    $schema->string('name')->optional();
    $schema->integer('age')->required();

    expect($schema->toArray())->toBe([
        'type' => 'object',
        'properties' => [
            'name' => ['type' => ToolInputSchema::TYPE_STRING],
            'age' => ['type' => ToolInputSchema::TYPE_INTEGER],
        ],
        'required' => ['age'],
    ]);
});

test('raw property with complex schema', function () {
    $schema = new ToolInputSchema;

    $schema->raw('packages', [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => "The composer package name (e.g., 'symfony/console')",
                ],
                'version' => [
                    'type' => 'string',
                    'description' => "The package version (e.g., '^6.0', '~5.4.0', 'dev-main')",
                ],
            ],
            'required' => ['name', 'version'],
            'additionalProperties' => false,
        ],
        'description' => 'List of Composer packages with their versions',
    ]);

    expect($schema->toArray())->toBe([
        'type' => 'object',
        'properties' => [
            'packages' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => "The composer package name (e.g., 'symfony/console')",
                        ],
                        'version' => [
                            'type' => 'string',
                            'description' => "The package version (e.g., '^6.0', '~5.4.0', 'dev-main')",
                        ],
                    ],
                    'required' => ['name', 'version'],
                    'additionalProperties' => false,
                ],
                'description' => 'List of Composer packages with their versions',
            ],
        ],
    ]);
});

test('raw property works with other methods', function () {
    $schema = new ToolInputSchema;

    $schema->string('name')->description('User name')->required();
    $schema->raw('packages', [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'version' => ['type' => 'string'],
            ],
        ],
    ])->required();
    $schema->boolean('active');

    expect($schema->toArray())->toBe([
        'type' => 'object',
        'properties' => [
            'name' => ['type' => ToolInputSchema::TYPE_STRING, 'description' => 'User name'],
            'packages' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'version' => ['type' => 'string'],
                    ],
                ],
            ],
            'active' => ['type' => ToolInputSchema::TYPE_BOOLEAN],
        ],
        'required' => ['name', 'packages'],
    ]);
});
