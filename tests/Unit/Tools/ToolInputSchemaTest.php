<?php

namespace Laravel\Mcp\Tests\Unit\Tools;

use Laravel\Mcp\Server\Tools\ToolInputSchema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ToolInputSchemaTest extends TestCase
{
    #[Test]
    public function sets_string_type_correctly()
    {
        $schema = new ToolInputSchema;

        $schema->string('name');

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => ToolInputSchema::TYPE_STRING],
            ],
        ], $schema->toArray());
    }

    #[Test]
    public function sets_integer_type_correctly()
    {
        $schema = new ToolInputSchema;

        $schema->integer('age');

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'age' => ['type' => ToolInputSchema::TYPE_INTEGER],
            ],
        ], $schema->toArray());
    }

    #[Test]
    public function sets_number_type_correctly()
    {
        $schema = new ToolInputSchema;

        $schema->number('price');

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'price' => ['type' => ToolInputSchema::TYPE_NUMBER],
            ],
        ], $schema->toArray());
    }

    #[Test]
    public function sets_boolean_type_correctly()
    {
        $schema = new ToolInputSchema;

        $schema->boolean('is_active');

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'is_active' => ['type' => ToolInputSchema::TYPE_BOOLEAN],
            ],
        ], $schema->toArray());
    }

    #[Test]
    public function description_mutates_last_property_only()
    {
        $schema = new ToolInputSchema;
        $schema->string('name')->description('The name of the item.');
        $schema->integer('quantity')->description('The number of items.');

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => ToolInputSchema::TYPE_STRING, 'description' => 'The name of the item.'],
                'quantity' => ['type' => ToolInputSchema::TYPE_INTEGER, 'description' => 'The number of items.'],
            ],
        ], $schema->toArray());

        $schema->description('New description');

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => ToolInputSchema::TYPE_STRING, 'description' => 'The name of the item.'],
                'quantity' => ['type' => ToolInputSchema::TYPE_INTEGER, 'description' => 'New description'],
            ],
        ], $schema->toArray());
    }

    #[Test]
    public function description_without_property_does_nothing()
    {
        $schema = new ToolInputSchema;

        $schema->description('This should not be added.');

        $this->assertEquals('{"type":"object","properties":{}}', json_encode($schema->toArray()));
    }

    #[Test]
    public function required_accumulates_without_duplicates()
    {
        $schema = new ToolInputSchema;

        $schema->string('name')->required();
        $schema->string('name')->required(); // Duplicate
        $schema->integer('age')->required();

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => ToolInputSchema::TYPE_STRING],
                'age' => ['type' => ToolInputSchema::TYPE_INTEGER],
            ],
            'required' => ['name', 'age'],
        ], $schema->toArray());
    }

    #[Test]
    public function to_array_omits_required_when_no_fields_marked_required()
    {
        $schema = new ToolInputSchema;

        $schema->string('name');
        $schema->integer('age');

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => ToolInputSchema::TYPE_STRING],
                'age' => ['type' => ToolInputSchema::TYPE_INTEGER],
            ],
        ], $schema->toArray());
    }

    #[Test]
    public function it_can_be_used_fluently()
    {
        $schema = new ToolInputSchema;

        $schema->string('name')->description('User name')->required();
        $schema->integer('level')->description('User level')->required();
        $schema->boolean('verified')->description('Is user verified');

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => ToolInputSchema::TYPE_STRING, 'description' => 'User name'],
                'level' => ['type' => ToolInputSchema::TYPE_INTEGER, 'description' => 'User level'],
                'verified' => ['type' => ToolInputSchema::TYPE_BOOLEAN, 'description' => 'Is user verified'],
            ],
            'required' => ['name', 'level'],
        ], $schema->toArray());
    }

    #[Test]
    public function required_without_property_does_nothing()
    {
        $schema = new ToolInputSchema;

        $schema->required();

        $this->assertEquals('{"type":"object","properties":{}}', json_encode($schema->toArray()));
    }

    #[Test]
    public function properties_can_be_marked_optional()
    {
        $schema = new ToolInputSchema;

        $schema->string('name')->optional();
        $schema->integer('age')->required();

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => ToolInputSchema::TYPE_STRING],
                'age' => ['type' => ToolInputSchema::TYPE_INTEGER],
            ],
            'required' => ['age'],
        ], $schema->toArray());
    }

    #[Test]
    public function raw_property_with_complex_schema()
    {
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

        $this->assertSame([
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
        ], $schema->toArray());
    }

    #[Test]
    public function raw_property_works_with_other_methods()
    {
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

        $this->assertSame([
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
        ], $schema->toArray());
    }
}
