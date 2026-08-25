<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Testing;

use Illuminate\Support\Arr;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Laravel\Mcp\Server\Primitive;
use PHPUnit\Framework\Assert;

class TestListResponse
{
    use Conditionable;
    use Macroable;

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(
        protected array $items,
    ) {}

    /**
     * @param  array<class-string<Primitive>>|class-string<Primitive>  $classes
     */
    public function assertRegistered(array|string $classes): static
    {
        $itemNames = Arr::pluck($this->items, 'name');
        $classes = is_array($classes) ? $classes : [$classes];

        foreach ($classes as $class) {
            $name = (new $class)->name();

            Assert::assertContains($name, $itemNames, "The expected class [{$class}] was not found in the response content.");
        }

        // @phpstan-ignore-next-line
        Assert::assertTrue(true);

        return $this;
    }

    /**
     * @param  array<class-string<Primitive>>|class-string<Primitive>  $classes
     */
    public function assertNotRegistered(array|string $classes): static
    {
        $itemNames = Arr::pluck($this->items, 'name');
        $classes = is_array($classes) ? $classes : [$classes];

        foreach ($classes as $class) {
            $name = (new $class)->name();

            Assert::assertNotContains($name, $itemNames, "The expected class [{$class}] was found in the response content.");
        }

        // @phpstan-ignore-next-line
        Assert::assertTrue(true);

        return $this;
    }

    public function dd(): void
    {
        dd($this->items);
    }

    public function dump(): static
    {
        dump($this->items);

        return $this;
    }
}
