<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Concerns;

use InvalidArgumentException;
use Laravel\Mcp\Server\Contracts\Annotation;
use ReflectionAttribute;
use ReflectionClass;

trait HasAnnotations
{
    /**
     * @return array<string, mixed>
     */
    public function annotations(): array
    {
        $reflection = new ReflectionClass($this);

        /** @var \Illuminate\Support\Collection<int, Annotation> $annotations */
        $annotations = collect($reflection->getAttributes())
            ->map(fn (ReflectionAttribute $attributeReflection): object => $attributeReflection->newInstance())
            ->filter(fn (object $attribute): bool => $attribute instanceof Annotation)
            ->values();

        // @phpstan-ignore argument.templateType
        return $annotations
            ->each(function (Annotation $attribute): void {
                $this->validateAnnotationUsage($attribute);
            })
            ->mapWithKeys(fn (Annotation $attribute): array => [
                $attribute->key() => $attribute->value, // @phpstan-ignore property.notFound
            ])
            ->all();
    }

    private function validateAnnotationUsage(Annotation $attribute): void
    {
        foreach ($attribute->allowedOn() as $allowedClass) {
            if ($this instanceof $allowedClass) {
                return;
            }
        }

        $allowedClasses = implode(', ', $attribute->allowedOn());

        throw new InvalidArgumentException(
            sprintf(
                'Annotation [%s] cannot be used on [%s]. Allowed on: [%s]',
                $attribute::class,
                $this::class,
                $allowedClasses
            )
        );
    }
}
