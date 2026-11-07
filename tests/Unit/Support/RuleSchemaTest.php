<?php

use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Support\RuleSchema;

enum RuleSchemaStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

enum RuleSchemaPriority: int
{
    case Low = 1;
    case High = 2;
}

it('builds an object schema from rule strings', function (): void {
    $schema = RuleSchema::from([
        'title' => 'required|string|min:3|max:120',
        'views' => 'integer|min:0',
        'rating' => 'nullable|numeric|between:0,5|multiple_of:0.5',
        'published' => 'boolean',
    ]);

    expect($schema)->toBeInstanceOf(ObjectType::class)
        ->and($schema->toArray())->toBe([
            'properties' => [
                'title' => ['minLength' => 3, 'maxLength' => 120, 'type' => 'string'],
                'views' => ['minimum' => 0, 'type' => 'integer'],
                'rating' => ['minimum' => 0, 'maximum' => 5, 'multipleOf' => 0.5, 'type' => ['number', 'null']],
                'published' => ['type' => 'boolean'],
            ],
            'type' => 'object',
            'required' => ['title'],
        ]);
});

it('accepts rule builders and rule objects', function (): void {
    $schema = RuleSchema::from([
        'name' => ['required', Rule::string()->min(2)->max(50)],
        'age' => Rule::numeric()->integer()->min(18),
        'tags' => Rule::array()->max(5),
        'status' => ['required', Rule::in(['draft', 'published'])],
        'email' => Rule::email(),
        'website' => ['nullable', Rule::url()],
    ]);

    expect($schema->toArray())->toBe([
        'properties' => [
            'name' => ['minLength' => 2, 'maxLength' => 50, 'type' => 'string'],
            'age' => ['minimum' => 18, 'type' => 'integer'],
            'tags' => ['maxItems' => 5, 'type' => 'array'],
            'status' => ['enum' => ['draft', 'published'], 'type' => 'string'],
            'email' => ['format' => 'email', 'type' => 'string'],
            'website' => ['format' => 'uri', 'type' => ['string', 'null']],
        ],
        'type' => 'object',
        'required' => ['name', 'status'],
    ]);
});

it('maps enum rules to enum values', function (): void {
    $schema = RuleSchema::from([
        'status' => Rule::enum(RuleSchemaStatus::class),
        'priority' => ['integer', Rule::enum(RuleSchemaPriority::class)],
        'limited' => Rule::enum(RuleSchemaStatus::class)->only(RuleSchemaStatus::Draft),
    ]);

    expect($schema->toArray()['properties'])->toBe([
        'status' => ['enum' => ['draft', 'published'], 'type' => 'string'],
        'priority' => ['enum' => [1, 2], 'type' => 'integer'],
        'limited' => ['enum' => ['draft'], 'type' => 'string'],
    ]);
});

it('nests dotted attributes into objects', function (): void {
    $schema = RuleSchema::from([
        'author' => 'required|array',
        'author.name' => 'required|string|max:50',
        'author.email' => 'required|email',
        'author.address.city' => 'string',
    ]);

    expect($schema->toArray())->toBe([
        'properties' => [
            'author' => [
                'properties' => [
                    'name' => ['maxLength' => 50, 'type' => 'string'],
                    'email' => ['format' => 'email', 'type' => 'string'],
                    'address' => [
                        'properties' => [
                            'city' => ['type' => 'string'],
                        ],
                        'type' => 'object',
                    ],
                ],
                'type' => 'object',
                'required' => ['name', 'email'],
            ],
        ],
        'type' => 'object',
        'required' => ['author'],
    ]);
});

it('maps wildcard attributes to array items', function (): void {
    $schema = RuleSchema::from([
        'tags' => 'array|min:1|max:5',
        'tags.*' => 'string|distinct|max:20',
        'attachments.*.url' => 'required|url',
        'attachments.*.size' => 'integer',
        'matrix.*.*' => 'numeric',
    ]);

    expect($schema->toArray()['properties'])->toBe([
        'tags' => [
            'minItems' => 1,
            'maxItems' => 5,
            'items' => ['maxLength' => 20, 'type' => 'string'],
            'uniqueItems' => true,
            'type' => 'array',
        ],
        'attachments' => [
            'items' => [
                'properties' => [
                    'url' => ['format' => 'uri', 'type' => 'string'],
                    'size' => ['type' => 'integer'],
                ],
                'type' => 'object',
                'required' => ['url'],
            ],
            'type' => 'array',
        ],
        'matrix' => [
            'items' => [
                'items' => ['type' => 'number'],
                'type' => 'array',
            ],
            'type' => 'array',
        ],
    ]);
});

it('sizes bounds in the unit of the type', function (): void {
    $schema = RuleSchema::from([
        'name' => 'string|size:8',
        'count' => 'integer|between:1.5,9.5',
        'items' => 'array|size:3',
        'ratio' => 'decimal:0,2|max:1.5',
    ]);

    expect($schema->toArray()['properties'])->toBe([
        'name' => ['minLength' => 8, 'maxLength' => 8, 'type' => 'string'],
        'count' => ['minimum' => 2, 'maximum' => 9, 'type' => 'integer'],
        'items' => ['minItems' => 3, 'maxItems' => 3, 'type' => 'array'],
        'ratio' => ['maximum' => 1.5, 'type' => 'number'],
    ]);
});

it('strips delimiters from regex patterns', function (): void {
    $schema = RuleSchema::from([
        'slug' => ['regex:/^[a-z0-9-]+$/i'],
        'code' => [Rule::regex('{^[A-Z]{3}$}')],
        'mixed' => ['regex:/^(a|b)$/'],
    ]);

    expect($schema->toArray()['properties'])->toBe([
        'slug' => ['pattern' => '^[a-z0-9-]+$', 'type' => 'string'],
        'code' => ['pattern' => '^[A-Z]{3}$', 'type' => 'string'],
        'mixed' => ['pattern' => '^(a|b)$', 'type' => 'string'],
    ]);
});

it('maps date and identifier rules to formats', function (): void {
    $schema = RuleSchema::from([
        'published_at' => 'date',
        'birthday' => 'date_format:Y-m-d',
        'uuid' => 'uuid',
        'ip' => 'ipv4',
    ]);

    expect($schema->toArray()['properties'])->toBe([
        'published_at' => ['format' => 'date-time', 'type' => 'string'],
        'birthday' => ['format' => 'date', 'type' => 'string'],
        'uuid' => ['format' => 'uuid', 'type' => 'string'],
        'ip' => ['format' => 'ipv4', 'type' => 'string'],
    ]);
});

it('ignores rules without a schema equivalent', function (): void {
    $schema = RuleSchema::from([
        'email' => ['required', 'email', 'unique:users,email', Rule::exists('users', 'email')],
        'password' => 'required|string|confirmed|min:8',
        'token' => [fn ($attribute, $value, $fail) => $fail('Nope.'), 'string'],
        'role' => ['required_if:type,admin', 'string'],
        'items' => [Rule::forEach(fn (): array => ['string']), 'array'],
    ]);

    expect($schema->toArray())->toBe([
        'properties' => [
            'email' => ['format' => 'email', 'type' => 'string'],
            'password' => ['minLength' => 8, 'type' => 'string'],
            'token' => ['type' => 'string'],
            'role' => ['type' => 'string'],
            'items' => ['type' => 'array'],
        ],
        'type' => 'object',
        'required' => ['email', 'password'],
    ]);
});

it('treats untyped attributes as strings', function (): void {
    $schema = RuleSchema::from([
        'anything' => 'required',
        'short' => 'max:10',
    ]);

    expect($schema->toArray()['properties'])->toBe([
        'anything' => ['type' => 'string'],
        'short' => ['maxLength' => 10, 'type' => 'string'],
    ]);
});

it('treats keyed array rules as objects', function (): void {
    $schema = RuleSchema::from([
        'options' => Rule::array(['color', 'size']),
    ]);

    expect($schema->toArray()['properties'])->toBe([
        'options' => [
            'properties' => [
                'color' => ['type' => 'string'],
                'size' => ['type' => 'string'],
            ],
            'type' => 'object',
        ],
    ]);
});
