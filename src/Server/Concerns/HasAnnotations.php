<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Concerns;

use InvalidArgumentException;
use Laravel\Mcp\Server\Annotations\Annotation;
use Laravel\Mcp\Server\Contracts\Annotation as AnnotationContract;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\ToolAnnotation;
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

    /**
     * @return array<class-string, class-string>
     */
    private function annotationMapping(): array
    {
        return [
            Resource::class => Annotation::class,
            Tool::class => ToolAnnotation::class,
        ];
    }

    private function validateAnnotationUsage(AnnotationContract $attribute): void
    {
        $mapping = $this->annotationMapping();

        foreach ($mapping as $primitiveClass => $annotationBaseClass) {
            if ($this instanceof $primitiveClass && $attribute instanceof $annotationBaseClass) {
                return;
            }
        }

        $allowedAnnotations = collect($mapping)
            ->filter(fn ($annotationClass, $primitiveClass): bool => $this instanceof $primitiveClass)
            ->values()
            ->all();

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
}
