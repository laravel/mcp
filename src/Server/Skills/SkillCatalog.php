<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Skills;

use Closure;
use Generator;
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
     * @var list<InvalidSkillException>
     */
    protected array $errors = [];

    /**
     * @param  iterable<int|string, mixed>  $sources
     */
    public function __construct(protected iterable $sources)
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
     * @return list<InvalidSkillException>
     */
    public function errors(): array
    {
        $this->entries();

        return $this->errors;
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

        foreach ($this->expand($this->sources) as $skill) {
            if (! $skill->eligibleForRegistration()) {
                continue;
            }

            try {
                $resources = $skill->resources();
            } catch (InvalidSkillException $invalidSkillException) {
                $this->errors[] = $invalidSkillException;
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
     * @param  iterable<int|string, mixed>  $sources
     * @return Generator<int, Skill>
     */
    protected function expand(iterable $sources): Generator
    {
        foreach ($sources as $source) {
            match (true) {
                $source instanceof Skill => yield $source,
                is_string($source) && is_subclass_of($source, Skill::class) => yield Container::getInstance()->make($source),
                is_string($source) => yield from $this->discover($source),
                $source instanceof Closure => yield from $this->expand($this->evaluate($source)),
                default => throw new InvalidArgumentException('Skill sources must be directories, Skill classes or instances, or closures returning them.'),
            };
        }
    }

    /**
     * @return iterable<int|string, mixed>
     */
    protected function evaluate(Closure $source): iterable
    {
        $result = Container::getInstance()->call($source);

        return match (true) {
            $result === null => [],
            is_iterable($result) => $result,
            default => [$result],
        };
    }

    /**
     * @return Generator<int, Skill>
     */
    protected function discover(string $directory): Generator
    {
        $directory = rtrim(SkillDirectory::resolve($directory), '/\\');

        if (! is_dir($directory) || is_file($directory.DIRECTORY_SEPARATOR.'SKILL.md')) {
            yield Skill::fromDirectory($directory);

            return;
        }

        $children = scandir($directory) ?: [];
        sort($children, SORT_STRING);

        foreach ($children as $child) {
            $path = $directory.DIRECTORY_SEPARATOR.$child;

            if (! str_starts_with($child, '.') && is_dir($path) && file_exists($path.DIRECTORY_SEPARATOR.'SKILL.md')) {
                yield Skill::fromDirectory($path);
            }
        }
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
