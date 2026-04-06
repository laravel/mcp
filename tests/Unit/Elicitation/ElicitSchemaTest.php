<?php

use Laravel\Mcp\Server\Elicitation\ElicitSchema;
use Laravel\Mcp\Server\Elicitation\Fields\BooleanField;
use Laravel\Mcp\Server\Elicitation\Fields\EnumField;
use Laravel\Mcp\Server\Elicitation\Fields\IntegerField;
use Laravel\Mcp\Server\Elicitation\Fields\MultiEnumField;
use Laravel\Mcp\Server\Elicitation\Fields\NumberField;
use Laravel\Mcp\Server\Elicitation\Fields\StringField;

it('creates string fields', function (): void {
    $schema = new ElicitSchema;

    expect($schema->string('Name'))->toBeInstanceOf(StringField::class);
});

it('creates number fields', function (): void {
    $schema = new ElicitSchema;

    expect($schema->number('Age'))->toBeInstanceOf(NumberField::class);
});

it('creates integer fields', function (): void {
    $schema = new ElicitSchema;

    expect($schema->integer('Count'))->toBeInstanceOf(IntegerField::class);
});

it('creates boolean fields', function (): void {
    $schema = new ElicitSchema;

    expect($schema->boolean('Active'))->toBeInstanceOf(BooleanField::class);
});

it('creates enum fields', function (): void {
    $schema = new ElicitSchema;

    expect($schema->enum('Color', ['red', 'blue']))->toBeInstanceOf(EnumField::class);
});

it('creates multi-enum fields', function (): void {
    $schema = new ElicitSchema;

    expect($schema->multiEnum('Colors', ['red', 'blue']))->toBeInstanceOf(MultiEnumField::class);
});
