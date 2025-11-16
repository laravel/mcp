<?php

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

it('returns no meta by default', function (): void {
    $prompt = new class extends Prompt
    {
        public function description(): string
        {
            return 'Test prompt';
        }

        public function handle(): Response
        {
            return Response::text('Hello');
        }
    };

    expect($prompt->meta())->toBeNull()
        ->and($prompt->toArray())->not->toHaveKey('_meta');
});

it('can have custom meta', function (): void {
    $prompt = new class extends Prompt
    {
        protected ?array $meta = [
            'category' => 'greeting',
            'tags' => ['hello', 'welcome'],
        ];

        public function description(): string
        {
            return 'Test prompt';
        }

        public function handle(): Response
        {
            return Response::text('Hello');
        }
    };

    expect($prompt->toArray())
        ->toHaveKey('_meta')
        ->_meta->toEqual([
            'category' => 'greeting',
            'tags' => ['hello', 'welcome'],
        ]);
});

it('includes meta in array representation with other fields', function (): void {
    $prompt = new class extends Prompt
    {
        protected string $name = 'greet';

        protected string $title = 'Greeting Prompt';

        protected string $description = 'A friendly greeting';

        protected ?array $meta = [
            'version' => '1.0',
        ];

        public function handle(): Response
        {
            return Response::text('Hello');
        }

        public function arguments(): array
        {
            return [
                new Argument('name', 'User name', true),
            ];
        }
    };

    $array = $prompt->toArray();

    expect($array)
        ->toHaveKey('name', 'greet')
        ->toHaveKey('title', 'Greeting Prompt')
        ->toHaveKey('description', 'A friendly greeting')
        ->toHaveKey('arguments')
        ->toHaveKey('_meta')
        ->and($array)
        ->_meta->toEqual(['version' => '1.0'])
        ->arguments->toHaveCount(1);

});
