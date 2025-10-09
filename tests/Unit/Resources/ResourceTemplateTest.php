<?php

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\ResourceTemplate;

it('returns a valid resource template with uriTemplate', function (): void {
    $resourceTemplate = new class extends ResourceTemplate
    {
        protected string $uriTemplate = 'file://resources/test/{id}';

        public function description(): string
        {
            return 'A test resource template.';
        }

        public function handle(): Response
        {
            return Response::text('This is a test resource template.');
        }
    };

    expect($resourceTemplate->uriTemplate())->toBe('file://resources/test/{id}')
        ->and($resourceTemplate->toArray())->toMatchArray([
            'uriTemplate' => 'file://resources/test/{id}',
            'name' => $resourceTemplate->name(),
            'title' => $resourceTemplate->title(),
            'description' => 'A test resource template.',
            'mimeType' => 'text/plain',
        ]);
});

it('generates default uriTemplate from class name', function (): void {
    $resourceTemplate = new class extends ResourceTemplate
    {
        public function description(): string
        {
            return 'A test resource template.';
        }

        public function handle(): Response
        {
            return Response::text('This is a test resource template.');
        }
    };

    expect($resourceTemplate->uriTemplate())->toContain('{id}')
        ->and($resourceTemplate->uriTemplate())->toStartWith('file://resources/');
});

it('can override mimeType', function (): void {
    $resourceTemplate = new class extends ResourceTemplate
    {
        protected string $uriTemplate = 'file://resources/document/{path}';

        protected string $mimeType = 'application/json';

        public function description(): string
        {
            return 'A JSON resource template.';
        }

        public function handle(): Response
        {
            return Response::text('{"key": "value"}');
        }
    };

    expect($resourceTemplate->mimeType())->toBe('application/json');
});

it('uses default text/plain mimeType when not specified', function (): void {
    $resourceTemplate = new class extends ResourceTemplate
    {
        public function description(): string
        {
            return 'A test resource template.';
        }

        public function handle(): Response
        {
            return Response::text('This is a test.');
        }
    };

    expect($resourceTemplate->mimeType())->toBe('text/plain');
});

it('toMethodCall returns uriTemplate', function (): void {
    $resourceTemplate = new class extends ResourceTemplate
    {
        protected string $uriTemplate = 'file://resources/user/{userId}/profile';

        public function description(): string
        {
            return 'User profile resource template.';
        }

        public function handle(): Response
        {
            return Response::text('User profile');
        }
    };

    expect($resourceTemplate->toMethodCall())->toBe([
        'uriTemplate' => 'file://resources/user/{userId}/profile',
    ]);
});

test('toArray includes all required fields', function (): void {
    $resourceTemplate = new class extends ResourceTemplate
    {
        protected string $name = 'test-template';

        protected string $title = 'Test Template';

        protected string $description = 'A test resource template';

        protected string $uriTemplate = 'file://resources/item/{id}';

        protected string $mimeType = 'application/json';

        public function handle(): Response
        {
            return Response::text('{}');
        }
    };

    $array = $resourceTemplate->toArray();

    expect($array)->toHaveKeys(['name', 'title', 'description', 'uriTemplate', 'mimeType'])
        ->and($array['name'])->toBe('test-template')
        ->and($array['title'])->toBe('Test Template')
        ->and($array['description'])->toBe('A test resource template')
        ->and($array['uriTemplate'])->toBe('file://resources/item/{id}')
        ->and($array['mimeType'])->toBe('application/json');
});
