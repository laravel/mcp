<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Annotations;

use Attribute;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Laravel\Mcp\Enums\Role;

#[Attribute(Attribute::TARGET_CLASS)]
class Audience extends Annotation
{
    /** @var array<int,string> */
    public array $value;

    /**
     * @param  Role|array<int, Role>  $roles
     */
    public function __construct(Role|array $roles)
    {
        $roles = Arr::wrap($roles);

        foreach ($roles as $role) {
            if (! $role instanceof Role) {
                throw new InvalidArgumentException(
                    'All values of '.Audience::class.' attributes must be instances of '.Role::class
                );
            }
        }

        $this->value = array_map(fn (Role $role) => $role->value, $roles);
    }

    public function key(): string
    {
        return 'audience';
    }
}
