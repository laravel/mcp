<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use ReflectionAttribute;
use ReflectionClass;

class AuthorizationEvaluator
{
    /**
     * @param object $subject
     * @param Request $request
     * @return bool
     */
    public function evaluate(object $subject, Request $request): bool
    {
        $requiredScopes = $this->extractAnnotationValues($subject, 'required_scopes');
        $requiredAbilities = $this->extractAnnotationValues($subject, 'required_abilities');

        $user = $this->resolveUser($request);

        $missingScopes = $this->missingScopes($user, $requiredScopes);
        $missingAbilities = $this->missingAbilities($user, $requiredAbilities);

        return ($missingScopes === []) && ($missingAbilities === []);
    }

    protected function resolveUser(Request $request): ?Authenticatable
    {
        return $request->user();
    }

    /**
     * @param  array<int,string>  $required
     * @return array<int,string>
     */
    protected function missingScopes(?Authenticatable $user, array $required): array
    {
        if ($required === []) {
            return [];
        }

        // If there is no user, scopes cannot be satisfied
        if (! $user instanceof \Illuminate\Contracts\Auth\Authenticatable) {
            return $required;
        }

        $missing = [];
        foreach ($required as $scope) {
            // Sanctum & Passport both expose tokenCan on the resolved user context
            if (! method_exists($user, 'tokenCan') || $user->tokenCan($scope) !== true) {
                $missing[] = $scope;
            }
        }

        return $missing;
    }

    /**
     * @param  array<int,string>  $required
     * @return array<int,string>
     */
    protected function missingAbilities(?Authenticatable $user, array $required): array
    {
        if ($required === []) {
            return [];
        }

        if (! $user instanceof \Illuminate\Contracts\Auth\Authenticatable) {
            return $required;
        }

        $missing = [];
        foreach ($required as $ability) {
            if (! method_exists($user, 'can') || $user->can($ability) !== true) {
                $missing[] = $ability;
            }
        }

        return $missing;
    }

    /**
     * Extract attribute annotation values directly via reflection to avoid coupling with toArray.
     *
     * @return array<int, string>
     */
    protected function extractAnnotationValues(object $subject, string $key): array
    {
        $reflection = new ReflectionClass($subject);
        $attributes = $reflection->getAttributes();

        $values = [];
        foreach ($attributes as $attribute) {
            // Avoid strict generic typing on ReflectionAttribute; instance is safe to construct
            $instance = $attribute->newInstance();
            if (method_exists($instance, 'key') && $instance->key() === $key) {
                // Tool::annotations() expects a magic ->value; mirror that here
                $v = $instance->value ?? [];
                $values = array_merge($values, is_array($v) ? $v : [$v]);
            }
        }

        // Normalize unique string list
        return array_values(array_unique(Arr::where($values, fn ($v): bool => is_string($v) && $v !== '')));
    }
}
