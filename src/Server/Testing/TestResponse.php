<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Testing;

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Mcp\Server\Primitive;
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
        protected Primitive $premitive,
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
        collect(is_array($text) ? $text : [$text])
            ->each($this->assertText(...));

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

    public function assertAuthenticated(?string $guard = null): static
    {
        Assert::assertTrue($this->isAuthenticated($guard), 'The user is not authenticated');

        return $this;
    }

    public function assertGuest(?string $guard = null): static
    {
        Assert::assertFalse($this->isAuthenticated($guard), 'The user is authenticated');

        return $this;
    }

    public function assertAuthenticatedAs(Authenticatable $user, ?string $guard = null): static
    {
        $expected = Container::getInstance()->make('auth')->guard($guard)->user();

        Assert::assertNotNull($expected, 'The current user is not authenticated.');

        Assert::assertInstanceOf(
            $expected::class, $user,
            'The currently authenticated user is not who was expected'
        );

        Assert::assertSame(
            $expected->getAuthIdentifier(), $user->getAuthIdentifier(),
            'The currently authenticated user is not who was expected'
        );

        return $this;
    }

    protected function isAuthenticated(?string $guard = null): bool
    {
        return Container::getInstance()->make('auth')->guard($guard)->check();
    }
}
