<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Testing;

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Laravel\Mcp\Server\Primitive;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use PHPUnit\Framework\Assert;
use RuntimeException;

class TestResponse
{
    use Conditionable;
    use Macroable;

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
        $seeable = collect([
            ...$this->content(),
            ...$this->errors(),
        ])->filter()->unique()->values()->all();

        foreach (is_array($text) ? $text : [$text] as $segment) {
            foreach ($seeable as $message) {
                if (str_contains($message, $segment)) {
                    continue 2;
                }
            }

            // @phpstan-ignore-next-line
            Assert::assertTrue(false, "The expected text [{$segment}] was not found in the response content.");
        }

        // @phpstan-ignore-next-line
        Assert::assertTrue(true);

        return $this;
    }

    /**
     * @param  array<string>|string  $text
     */
    public function assertDontSee(array|string $text): static
    {
        $seeable = collect([
            ...$this->content(),
            ...$this->errors(),
        ])->filter()->unique()->values()->all();

        foreach (is_array($text) ? $text : [$text] as $segment) {
            foreach ($seeable as $message) {
                if (str_contains($message, $segment)) {
                    // @phpstan-ignore-next-line
                    Assert::assertTrue(false, "The unexpected text [{$segment}] was found in the response content.");

                    return $this;
                }
            }
        }

        // @phpstan-ignore-next-line
        Assert::assertTrue(true);

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
    public function assertSentNotification(string $method, ?array $params = null): static
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
        Assert::assertEmpty($this->errors());

        return $this;
    }

    /**
     * @param  array<string>  $messages
     */
    public function assertHasErrors(array $messages = []): static
    {
        $errors = $this->errors();

        Assert::assertNotEmpty($errors, 'The response has no errors.');

        foreach ($messages as $message) {
            foreach ($errors as $error) {
                if (str_contains($error, $message)) {
                    continue 2;
                }
            }

            Assert::fail("The expected error message [{$message}] was not found in the response.");
        }

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

    /**
     * @param  array<int, string>  $expectedValues
     */
    public function assertHasCompletions(array $expectedValues = []): static
    {
        $actualValues = $this->completionValues();

        Assert::assertNotNull(
            $this->response->toArray()['result']['completion'] ?? null,
            'No completion data found in response.'
        );

        foreach ($expectedValues as $expected) {
            Assert::assertContains(
                $expected,
                $actualValues,
                "Expected completion value [{$expected}] not found."
            );
        }

        return $this;
    }

    /**
     * @param  array<int, string>  $values
     */
    public function assertCompletionValues(array $values): static
    {
        Assert::assertEquals(
            $values,
            $this->completionValues(),
            'Completion values do not match expected values.'
        );

        return $this;
    }

    public function assertCompletionCount(int $count): static
    {
        $values = $this->completionValues();

        Assert::assertCount(
            $count,
            $values,
            "Expected {$count} completions, but got ".count($values)
        );

        return $this;
    }

    public function dd(): void
    {
        dd($this->response->toArray());
    }

    public function dump(): void
    {
        dump($this->response->toArray());
    }

    public function ddErrors(): void
    {
        dd($this->errors());
    }

    /**
     * @return array<int, string>
     */
    protected function content(): array
    {
        return (match (true) {
            // @phpstan-ignore-next-line
            $this->premitive instanceof Tool => collect($this->response->toArray()['result']['content'] ?? [])
                ->map(fn (array $message): string => $message['text'] ?? ''),
            // @phpstan-ignore-next-line
            $this->premitive instanceof Prompt => collect($this->response->toArray()['result']['messages'] ?? [])
                ->map(fn (array $message): array => $message['content'])
                ->map(fn (array $content): string => $content['text'] ?? ''),
            // @phpstan-ignore-next-line
            $this->premitive instanceof Resource => collect($this->response->toArray()['result']['contents'] ?? [])
                ->map(fn (array $item): string => $item['text'] ?? $item['blob'] ?? ''),
            default => throw new RuntimeException('This primitive type is not supported.'),
        })->filter()->unique()->values()->all();
    }

    /**
     * @return array<int, string>
     */
    protected function errors(): array
    {
        $response = $this->response->toArray();

        if (data_get($response, 'result.isError', false)) {
            return $this->content();
        }

        if (array_key_exists('error', $response)) {
            return [$response['error']['message']];
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    protected function completionValues(): array
    {
        $response = $this->response->toArray();

        return $response['result']['completion']['values'] ?? [];
    }
}
