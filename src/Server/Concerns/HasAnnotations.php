<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Concerns;

use InvalidArgumentException;
use Laravel\Mcp\Server\Contracts\Annotation as AnnotationContract;
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

        /** @var \Illuminate\Support\Collection<int, AnnotationContract> $annotations */
        $annotations = collect($reflection->getAttributes())
            ->map(fn (ReflectionAttribute $attributeReflection): object => $attributeReflection->newInstance())
            ->filter(fn (object $attribute): bool => $attribute instanceof AnnotationContract)
            ->values();

        // @phpstan-ignore argument.templateType
        return $annotations
            ->each(function (AnnotationContract $attribute): void {
                $this->validateAnnotationUsage($attribute);
            })
            ->mapWithKeys(fn (AnnotationContract $attribute): array => [
                $attribute->key() => $attribute->value, // @phpstan-ignore property.notFound
            ])
            ->all();
    }

    private function validateAnnotationUsage(AnnotationContract $attribute): void
    {
        $allowedAnnotations = $this->allowedAnnotations();

        foreach ($allowedAnnotations as $allowedAnnotationClass) {
            if ($attribute instanceof $allowedAnnotationClass) {
                return;
            }
        }

        $allowedClasses = empty($allowedAnnotations)
            ? 'none'
            : implode(', ', $allowedAnnotations);

        throw new InvalidArgumentException(
            sprintf(
                'Annotation [%s] cannot be used on [%s]. Allowed annotation types: [%s]',
                $attribute::class,
                $this::class,
                $allowedClasses
            )
        );
    }

    /**
     * @return array<int, class-string>
     */
    protected function allowedAnnotations(): array
    {
        return [];
    }
}
