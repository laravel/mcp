<?php

use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Arguments;
use Laravel\Mcp\Server\Prompts\PromptResult;
use Tests\Fixtures\ReviewMyCodePrompt;

class DummyPrompt extends Prompt
{
    protected string $description = 'A test prompt';

    public function handle(): PromptResult
    {
        return new PromptResult('Test content', 'Test description');
    }
}

function makePrompt(): Prompt
{
    return new DummyPrompt;
}

it('has expected default values', function () {
    $prompt = makePrompt();

    expect($prompt->name())->toBe('dummy-prompt');
    expect($prompt->title())->toBe('Dummy Prompt');
    expect($prompt->description())->toBe('A test prompt');
});

it('returns arguments', function () {
    $prompt = makePrompt();
    $arguments = $prompt->arguments();

    expect($arguments)->toBeInstanceOf(Arguments::class);
});

it('can be converted to array', function () {
    $prompt = makePrompt();
    $array = $prompt->toArray();

    expect($array)->toHaveKey('name');
    expect($array)->toHaveKey('title');
    expect($array)->toHaveKey('description');
    expect($array)->toHaveKey('arguments');

    expect($array['name'])->toBe('dummy-prompt');
    expect($array['title'])->toBe('Dummy Prompt');
    expect($array['description'])->toBe('A test prompt');
    expect($array['arguments'])->toBeArray();
});

it('can handle arguments', function () {
    $prompt = makePrompt();
    $result = $prompt->handle(['test' => 'value']);

    expect('Test description')->toEqual($result->toArray()['description']);
});

it('works with fixture prompt', function () {
    $prompt = new ReviewMyCodePrompt;

    expect($prompt->name())->toBe('review-my-code-prompt');
    expect($prompt->title())->toBe('Review My Code Prompt');

    $result = $prompt->handle();
    expect('Instructions for how to review my code')->toEqual($result->toArray()['description']);
    expect($result->toArray()['messages'])->toHaveCount(1);
});
