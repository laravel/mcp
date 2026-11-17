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
use RuntimeException;

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
     * @var list<Skill>|null
     */
    protected ?array $eligible = null;

    /**
     * @var array<string, SkillResource|null>
     */
    protected array $found = [];

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

    public function resource(string $uri): ?SkillResource
    {
        if (array_key_exists($uri, $this->found)) {
            return $this->found[$uri];
        }

        $match = null;

        foreach ($this->eligible() as $skill) {
            try {
                if (! str_starts_with($uri, $skill->fileUri(''))) {
                    continue;
                }

                $resources = $skill->resources();
            } catch (RuntimeException $runtimeException) {
                $this->report(InvalidSkillException::for($skill, $runtimeException));

                continue;
            }

            foreach ($resources as $resource) {
                if ($resource->uri() !== $uri) {
                    continue;
                }

                if ($uri === $skill->uri()) {
                    return $this->found[$uri] = $resource;
                }

                $match ??= $resource;
            }
        }

        return $this->found[$uri] = $match;
    }

    /**
     * @return array{uri: string, frontmatter: array<string, mixed>, resources: list<array{uri: string, digest: string, size: int}>}|null
     */
    public function manifest(Skill $skill): ?array
    {
        try {
            return $skill->toArray();
        } catch (InvalidSkillException $invalidSkillException) {
            $this->report($invalidSkillException);

            return null;
        }
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

        foreach ($this->eligible() as $skill) {
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

    /**
     * @return list<Skill>
     */
    protected function eligible(): array
    {
        return $this->eligible ??= array_values(array_filter(
            array_map($this->resolve(...), $this->sources),
            fn (Skill $skill): bool => $skill->eligibleForRegistration(),
        ));
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
