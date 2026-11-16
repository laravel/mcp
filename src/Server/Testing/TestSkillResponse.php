<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Testing;

use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Laravel\Mcp\Transport\JsonRpcResponse;
use PHPUnit\Framework\Assert;

class TestSkillResponse
{
    use Conditionable;
    use Macroable;

    public function __construct(
        protected string $uri,
        protected JsonRpcResponse $response,
    ) {
        //
    }

    public function assertOk(): static
    {
        Assert::assertSame([], $this->errors(), 'The response has errors.');

        return $this;
    }

    /**
     * @param  array<int, string>  $messages
     */
    public function assertHasErrors(array $messages = []): static
    {
        $errors = $this->errors();

        Assert::assertNotEmpty($errors, 'The response has no errors.');

        foreach ($messages as $message) {
            Assert::assertTrue(
                collect($errors)->contains(fn (string $error): bool => str_contains($error, $message)),
                "The expected error message [{$message}] was not found in the response.",
            );
        }

        return $this;
    }

    public function assertNotRegistered(): static
    {
        return $this->assertHasErrors(["Skill [{$this->uri}] not found."]);
    }

    public function assertName(string $name): static
    {
        Assert::assertSame($name, $this->frontmatter()['name'] ?? null, "The skill name does not match [{$name}].");

        return $this;
    }

    public function assertDescription(string $description): static
    {
        Assert::assertSame($description, $this->frontmatter()['description'] ?? null, "The skill description does not match [{$description}].");

        return $this;
    }

    /**
     * @param  array<string, mixed>  $frontmatter
     */
    public function assertFrontmatter(array $frontmatter): static
    {
        $actual = $this->frontmatter();

        foreach ($frontmatter as $key => $value) {
            Assert::assertArrayHasKey($key, $actual, "The skill frontmatter does not contain [{$key}].");
            Assert::assertEquals($value, $actual[$key], "The skill frontmatter [{$key}] does not match.");
        }

        return $this;
    }

    /**
     * @param  array<int, string>  $files
     */
    public function assertFiles(array $files): static
    {
        $base = substr($this->uri, 0, -strlen('SKILL.md'));
        $actual = array_map(
            fn (array $resource): string => rawurldecode(substr((string) $resource['uri'], strlen($base))),
            (array) ($this->skill()['resources'] ?? []),
        );

        sort($actual);
        sort($files);

        Assert::assertSame($files, $actual, 'The skill files do not match.');

        return $this;
    }

    public function dd(): never
    {
        dd($this->response->toArray());
    }

    public function dump(): static
    {
        dump($this->response->toArray());

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    protected function skill(): array
    {
        $skill = $this->response->toArray()['result']['skill'] ?? null;

        Assert::assertIsArray($skill, 'The response does not contain a skill.');

        return json_decode((string) json_encode($skill), true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function frontmatter(): array
    {
        return $this->skill()['frontmatter'] ?? [];
    }

    /**
     * @return array<int, string>
     */
    protected function errors(): array
    {
        $response = $this->response->toArray();

        return array_key_exists('error', $response) ? [(string) $response['error']['message']] : [];
    }
}
