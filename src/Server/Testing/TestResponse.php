<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Testing;

use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use PHPUnit\Framework\Assert;

class TestResponse
{
    protected JsonRpcResponse $response;

    /**
     * @var array<int, JsonRpcResponse>
     */
    protected array $notifications = [];

    /**
     * @param  iterable<int, JsonRpcResponse>|JsonRpcResponse  $response
     */
    public function __construct(
        protected Tool|Prompt|Resource $premitive,
        iterable|JsonRpcResponse $response,
    ) {
        $responses = is_iterable($response)
            ? iterator_to_array($response)
            : [$response];

        foreach ($responses as $response) {
            $content = $response->toArray();

            if (isset($content['id'])) {
                $this->response = $response;
            } else {
                $this->notifications[] = $response;
            }
        }
    }

    /**
     * @param  array<string>|string  $text
     */
    public function assertSee(array|string $text): static
    {
        $text = is_array($text) ? $text : [$text];

        foreach ($text as $t) {
            $this->assertText($t);
        }

        return $this;
    }

    public function assertText(string $text): static
    {
        $contents = array_map(fn (array $content) => $content['text'] ?? '', array_filter($this->response->toArray()['result']['content'], fn (array $content): bool => $content['type'] === 'text'));

        Assert::assertStringContainsString(
            $text,
            implode("\n", $contents),
            "The text [{$text}] was not found in the response.",
        );

        return $this;
    }

    public function assertTextCount(int $count): static
    {
        $contents = array_filter($this->response->toArray()['result']['content'], fn (array $content): bool => $content['type'] === 'text');

        Assert::assertCount($count, $contents, "The expected number of text contents [{$count}] does not match the actual count.");

        return $this;
    }

    public function assertNotificationCount(int $count): static
    {
        Assert::assertCount($count, $this->notifications, "The expected number of notifications [{$count}] does not match the actual count.");

        return $this;
    }

    /**
     * @param  array<string, mixed>|null  $params
     */
    public function assertNotification(string $method, ?array $params = null): static
    {
        foreach ($this->notifications as $notification) {
            $content = $notification->toArray();

            if ($content['method'] === $method && (is_array($params) === false || $content['params'] === $params)) {
                Assert::assertTrue(true); // @phpstan-ignore-line

                return $this;
            }
        }

        Assert::fail("The expected notification [{$method}], but it was not found.");
    }

    public function assertName(string $name): static
    {
        Assert::assertEquals(
            $name,
            $this->premitive->name(),
            "The expected name [{$name}] does not match the actual name [{$this->premitive->name()}].",
        );

        return $this;
    }

    public function assertTitle(string $title): static
    {
        Assert::assertEquals(
            $title,
            $this->premitive->title(),
            "The expected title [{$title}] does not match the actual title [{$this->premitive->title()}].",
        );

        return $this;
    }

    public function assertDescription(string $description): static
    {
        Assert::assertEquals(
            $description,
            $this->premitive->description(),
            "The expected description [{$description}] does not match the actual description [{$this->premitive->description()}].",
        );

        return $this;
    }

    public function assertOk(): static
    {
        return $this->assertHasNoErrors();
    }

    public function assertHasNoErrors(): static
    {
        $content = $this->response->toArray();

        Assert::assertFalse(
            data_get($content, 'result.isError', false),
            'The response contains errors.',
        );

        return $this;
    }

    /**
     * @param  array<string>  $messages
     */
    public function assertHasErrors(array $messages = []): static
    {
        $content = $this->response->toArray();

        Assert::assertTrue(
            data_get($content, 'result.isError', false),
            'The response does not contain any errors.',
        );

        $this->assertSee($messages);

        return $this;
    }
}
