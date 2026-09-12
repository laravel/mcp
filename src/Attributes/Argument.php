<?php

declare(strict_types=1);

namespace Laravel\Mcp\Attributes;

use Attribute;
use Illuminate\Container\BoundMethod;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Container\ContextualAttribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Argument implements ContextualAttribute
{
    public function __construct(public ?string $name = null) {}

    public static function resolve(self $attribute, Container $container, ?ReflectionParameter $parameter = null): mixed
    {
        $parameter ??= self::resolveReflectionParameter();

        if (! $parameter instanceof ReflectionParameter) {
            throw new RuntimeException('Unable to resolve the reflected parameter for the [Argument] attribute.');
        }

        /** @var Request $request */
        $request = $container->make(Request::class);
        $type = $parameter->getType();

        $key = $attribute->name ?? $parameter->getName();
        $arguments = $request->all();

        if (! array_key_exists($key, $arguments)) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            if ($type && $type->allowsNull()) {
                return null;
            }

            throw ValidationException::withMessages([
                $key => ["The {$key} field is required."],
            ]);
        }

        $value = $arguments[$key];

        if ($value === null) {
            return null;
        }

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return $value;
        }

        $class = $type->getName();

        if (! is_a($class, Model::class, true)) {
            return $value;
        }

        if ($value instanceof $class) {
            return $value;
        }

        $model = new $class;

        $resolved = $model->resolveRouteBinding(
            $value,
            $model->getRouteKeyName(),
        );

        if ($resolved === null) {
            throw (new ModelNotFoundException)->setModel($class, [$value]);
        }

        return $resolved;
    }

    private static function resolveReflectionParameter(): ?ReflectionParameter
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT) as $frame) {
            if (($frame['class'] ?? null) !== BoundMethod::class) {
                continue;
            }

            if (($frame['function'] ?? null) !== 'addDependencyForCallParameter') {
                continue;
            }

            $parameter = $frame['args'][1] ?? null;

            if ($parameter instanceof ReflectionParameter) {
                return $parameter;
            }
        }

        return null;
    }
}
