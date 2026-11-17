<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Skills;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Mcp\Exceptions\InvalidSkillException;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Skill;
use LogicException;

class SkillCatalog
{
    /**
     * @var array<string, true>
     */
    protected static array $reported = [];

    /**
     * @var list<array{0: Skill, 1: list<SkillResource>}>|null
     */
    protected ?array $entries = null;

    /**
     * @param  array<int, mixed>  $sources
     */
    public function __construct(protected array $sources)
    {
        //
    }

    /**
     * @return Collection<int, Skill>
     */
    public function skills(): Collection
    {
        return collect($this->entries())->map(fn (array $entry): Skill => $entry[0]);
    }

    /**
     * @param  Collection<int, Resource>  $registered
     * @return list<SkillResource>
     */
    public function resources(Collection $registered): array
    {
        $registeredUris = $registered->mapWithKeys(fn (Resource $resource): array => [$resource->uri() => true])->all();
        $resources = [];

        foreach ($this->entries() as [$skill, $files]) {
            foreach ($files as $resource) {
                $uri = $resource->uri();

                if (isset($registeredUris[$uri])) {
                    throw new LogicException("A resource is already registered at skill URI [{$uri}].");
                }

                if (isset($resources[$uri]) && $resources[$uri]->source() !== $resource->source()) {
                    throw new LogicException("Different skill files are registered at URI [{$uri}].");
                }

                if (! isset($resources[$uri]) || $uri === $skill->uri()) {
                    $resources[$uri] = $resource;
                }
            }
        }

        return array_values($resources);
    }

    public static function flushState(): void
    {
        static::$reported = [];
    }

    /**
     * @return list<array{0: Skill, 1: list<SkillResource>}>
     */
    protected function entries(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        $entries = [];
        $uris = [];

        foreach ($this->sources as $source) {
            $skill = $this->resolve($source);

            if (! $skill->eligibleForRegistration()) {
                continue;
            }

            try {
                $resources = $skill->resources();
            } catch (InvalidSkillException $invalidSkillException) {
                $this->report($invalidSkillException);

                continue;
            }

            if (isset($uris[$skill->uri()])) {
                throw new LogicException("Duplicate server skill URI [{$skill->uri()}].");
            }

            $uris[$skill->uri()] = true;
            $entries[] = [$skill, $resources];
        }

        return $this->entries = $entries;
    }

    protected function resolve(mixed $source): Skill
    {
        return match (true) {
            $source instanceof Skill => $source,
            is_string($source) && is_subclass_of($source, Skill::class) => Container::getInstance()->make($source),
            default => throw new InvalidArgumentException('Skill sources must be Skill classes or instances.'),
        };
    }

    protected function report(InvalidSkillException $exception): void
    {
        $key = $exception->getMessage();

        if (isset(static::$reported[$key])) {
            return;
        }

        static::$reported[$key] = true;

        report($exception);
    }
}
