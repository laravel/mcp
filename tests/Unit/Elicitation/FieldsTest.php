<?php

use Laravel\Mcp\Server\Elicitation\Fields\BooleanField;
use Laravel\Mcp\Server\Elicitation\Fields\EnumField;
use Laravel\Mcp\Server\Elicitation\Fields\IntegerField;
use Laravel\Mcp\Server\Elicitation\Fields\MultiEnumField;
use Laravel\Mcp\Server\Elicitation\Fields\NumberField;
use Laravel\Mcp\Server\Elicitation\Fields\StringField;

// StringField

it('builds a basic string field', function (): void {
    $field = new StringField('Name');

    expect($field->toArray())->toBe([
        'type' => 'string',
        'title' => 'Name',
    ]);
});

it('builds a string field with all options', function (): void {
    $field = (new StringField('Email'))
        ->description('Your email address')
        ->minLength(5)
        ->maxLength(100)
        ->pattern('^[^@]+@[^@]+$')
        ->format('email')
        ->default('user@example.com')
        ->required();

    expect($field->toArray())->toBe([
        'type' => 'string',
        'title' => 'Email',
        'description' => 'Your email address',
        'minLength' => 5,
        'maxLength' => 100,
        'pattern' => '^[^@]+@[^@]+$',
        'format' => 'email',
        'default' => 'user@example.com',
        '_required' => true,
    ]);
});

// NumberField

it('builds a basic number field', function (): void {
    $field = new NumberField('Price');

    expect($field->toArray())->toBe([
        'type' => 'number',
        'title' => 'Price',
    ]);
});

it('builds a number field with constraints', function (): void {
    $field = (new NumberField('Amount'))
        ->description('Total amount')
        ->min(0)
        ->max(1000.50)
        ->default(10.5)
        ->required();

    expect($field->toArray())->toBe([
        'type' => 'number',
        'title' => 'Amount',
        'description' => 'Total amount',
        'minimum' => 0,
        'maximum' => 1000.50,
        'default' => 10.5,
        '_required' => true,
    ]);
});

// IntegerField

it('builds a basic integer field', function (): void {
    $field = new IntegerField('Count');

    expect($field->toArray())->toBe([
        'type' => 'integer',
        'title' => 'Count',
    ]);
});

it('builds an integer field with constraints', function (): void {
    $field = (new IntegerField('Age'))
        ->description('Your age')
        ->min(18)
        ->max(120)
        ->default(25)
        ->required();

    expect($field->toArray())->toBe([
        'type' => 'integer',
        'title' => 'Age',
        'description' => 'Your age',
        'minimum' => 18,
        'maximum' => 120,
        'default' => 25,
        '_required' => true,
    ]);
});

// BooleanField

it('builds a basic boolean field', function (): void {
    $field = new BooleanField('Active');

    expect($field->toArray())->toBe([
        'type' => 'boolean',
        'title' => 'Active',
    ]);
});

it('builds a boolean field with options', function (): void {
    $field = (new BooleanField('Agree'))
        ->description('Agree to terms')
        ->default(false)
        ->required();

    expect($field->toArray())->toBe([
        'type' => 'boolean',
        'title' => 'Agree',
        'description' => 'Agree to terms',
        'default' => false,
        '_required' => true,
    ]);
});

// EnumField

it('builds a basic enum field', function (): void {
    $field = new EnumField('Plan', ['free', 'pro', 'enterprise']);

    expect($field->toArray())->toBe([
        'type' => 'string',
        'title' => 'Plan',
        'enum' => ['free', 'pro', 'enterprise'],
    ]);
});

it('builds a titled enum field', function (): void {
    $field = (new EnumField('Color', ['red', 'blue']))
        ->titled(['#FF0000' => 'Red', '#0000FF' => 'Blue'])
        ->default('#FF0000')
        ->required();

    expect($field->toArray())->toBe([
        'title' => 'Color',
        'oneOf' => [
            ['const' => '#FF0000', 'title' => 'Red'],
            ['const' => '#0000FF', 'title' => 'Blue'],
        ],
        'default' => '#FF0000',
        '_required' => true,
    ]);
});

it('builds an enum field with description', function (): void {
    $field = (new EnumField('Size', ['s', 'm', 'l']))
        ->description('Choose a size');

    expect($field->toArray())->toBe([
        'type' => 'string',
        'title' => 'Size',
        'enum' => ['s', 'm', 'l'],
        'description' => 'Choose a size',
    ]);
});

// MultiEnumField

it('builds a basic multi-enum field', function (): void {
    $field = new MultiEnumField('Tags', ['php', 'laravel', 'mcp']);

    expect($field->toArray())->toBe([
        'type' => 'array',
        'title' => 'Tags',
        'items' => ['type' => 'string', 'enum' => ['php', 'laravel', 'mcp']],
    ]);
});

it('builds a titled multi-enum field', function (): void {
    $field = (new MultiEnumField('Colors', ['red', 'blue']))
        ->titled(['#FF0000' => 'Red', '#0000FF' => 'Blue']);

    expect($field->toArray())->toBe([
        'type' => 'array',
        'title' => 'Colors',
        'items' => [
            'anyOf' => [
                ['const' => '#FF0000', 'title' => 'Red'],
                ['const' => '#0000FF', 'title' => 'Blue'],
            ],
        ],
    ]);
});

it('builds a multi-enum field with constraints', function (): void {
    $field = (new MultiEnumField('Features', ['a', 'b', 'c']))
        ->description('Select features')
        ->minItems(1)
        ->maxItems(2)
        ->default(['a'])
        ->required();

    expect($field->toArray())->toBe([
        'type' => 'array',
        'title' => 'Features',
        'items' => ['type' => 'string', 'enum' => ['a', 'b', 'c']],
        'description' => 'Select features',
        'minItems' => 1,
        'maxItems' => 2,
        'default' => ['a'],
        '_required' => true,
    ]);
});
