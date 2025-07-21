<?php

namespace Laravel\Mcp\Tests\Unit\Prompts;

use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Arguments;
use Laravel\Mcp\Server\Prompts\PromptResult;
use Laravel\Mcp\Tests\Fixtures\ReviewMyCodePrompt;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// A concrete implementation of the abstract Prompt class for test purposes.
class DummyPrompt extends Prompt
{
    protected string $description = 'A test prompt';

    public function handle(array $arguments): PromptResult
    {
        return new PromptResult('Test content', 'Test description');
    }
}

class PromptTest extends TestCase
{
    private function makePrompt(): Prompt
    {
        return new DummyPrompt;
    }

    #[Test]
    public function it_has_expected_default_values(): void
    {
        $prompt = $this->makePrompt();

        $this->assertSame('dummy-prompt', $prompt->name());
        $this->assertSame('Dummy Prompt', $prompt->title());
        $this->assertSame('A test prompt', $prompt->description());
    }

    #[Test]
    public function it_returns_arguments(): void
    {
        $prompt = $this->makePrompt();
        $arguments = $prompt->arguments();

        $this->assertInstanceOf(Arguments::class, $arguments);
    }

    #[Test]
    public function it_can_be_converted_to_array(): void
    {
        $prompt = $this->makePrompt();
        $array = $prompt->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('arguments', $array);

        $this->assertSame('dummy-prompt', $array['name']);
        $this->assertSame('Dummy Prompt', $array['title']);
        $this->assertSame('A test prompt', $array['description']);
        $this->assertIsArray($array['arguments']);
    }

    #[Test]
    public function it_can_handle_arguments(): void
    {
        $prompt = $this->makePrompt();
        $result = $prompt->handle(['test' => 'value']);

        $this->assertEquals($result->toArray()['description'], 'Test description');
    }

    #[Test]
    public function it_works_with_fixture_prompt(): void
    {
        $prompt = new ReviewMyCodePrompt;

        $this->assertSame('review-my-code-prompt', $prompt->name());
        $this->assertSame('Review My Code Prompt', $prompt->title());

        $result = $prompt->handle([]);
        $this->assertEquals($result->toArray()['description'], 'Instructions for how to review my code');
        $this->assertCount(1, $result->toArray()['messages']);
    }
}
