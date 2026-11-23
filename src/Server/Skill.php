<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use DateTimeInterface;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Concerns\ReadsAttributes;
use Laravel\Mcp\Server\Skills\SkillResource;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * @implements Arrayable<string, mixed>
 */
abstract class Skill implements Arrayable
{
    use ReadsAttributes;

    public const MAX_FILES = 512;

    public const MAX_SIZE = 16_777_216;

    abstract public function path(): string;

    public function directory(): string
    {
        $path = rtrim($this->path(), '/\\');

        if (str_starts_with($path, '/') || preg_match('~\A[a-z]:[\\\\/]~i', $path) === 1) {
            return $path;
        }

        return resource_path($path);
    }

    public function uri(): string
    {
        return $this->uriFor($this->frontmatter());
    }

    public function eligibleForRegistration(): bool
    {
        if (method_exists($this, 'shouldRegister')) {
            return (bool) Container::getInstance()->call([$this, 'shouldRegister']);
        }

        return true;
    }

    /**
     * @return array<string, mixed>&array{name: string, description: string}
     */
    public function frontmatter(): array
    {
        return once(fn (): array => $this->parse($this->read('SKILL.md')));
    }

    /**
     * @return list<string>
     */
    public function files(): array
    {
        $directory = $this->directory();

        if (! is_dir($directory)) {
            throw new InvalidArgumentException("The skill directory [{$directory}] does not exist.");
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            fn (SplFileInfo $file): bool => ! str_starts_with($file->getFilename(), '.') && ! $file->isLink(),
        ));

        $files = [];
        $size = 0;

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
            $size += $file->getSize();

            if (count($files) > self::MAX_FILES || $size > self::MAX_SIZE) {
                throw new InvalidArgumentException(sprintf(
                    'The skill [%s] must not contain more than %d files or %d MiB.',
                    $directory,
                    self::MAX_FILES,
                    self::MAX_SIZE / 1_048_576,
                ));
            }
        }

        sort($files);

        return $files;
    }

    public function read(string $file): string
    {
        $directory = realpath($this->directory());
        $path = realpath($this->directory().DIRECTORY_SEPARATOR.$file);

        if ($directory === false
            || $path === false
            || ! str_starts_with($path, $directory.DIRECTORY_SEPARATOR)
            || ! is_file($path)
            || filesize($path) > self::MAX_SIZE) {
            throw new InvalidArgumentException("The skill file [{$file}] is not readable.");
        }

        return (string) file_get_contents($path);
    }

    public function resource(string $file): SkillResource
    {
        return $this->makeResource($this->frontmatter(), $file);
    }

    public function resourceFor(string $uri): ?SkillResource
    {
        $frontmatter = $this->frontmatter();
        $base = rawurldecode($this->fileUri($this->uriFor($frontmatter), ''));
        $uri = rawurldecode($uri);

        if (! str_starts_with($uri, $base)) {
            return null;
        }

        $file = substr($uri, strlen($base));

        return in_array($file, $this->files(), true) ? $this->makeResource($frontmatter, $file) : null;
    }

    /**
     * @return array{uri: string, frontmatter: array<string, mixed>, resources: list<array{uri: string, digest: string, size: int}>}
     */
    public function toArray(): array
    {
        $contents = $this->read('SKILL.md');
        $frontmatter = $this->frontmatter();
        $uri = $this->uriFor($frontmatter);

        return [
            'uri' => $uri,
            'frontmatter' => $frontmatter,
            'resources' => array_map(function (string $file) use ($contents, $uri): array {
                $bytes = $file === 'SKILL.md' ? $contents : $this->read($file);

                return [
                    'uri' => $this->fileUri($uri, $file),
                    'digest' => 'sha256:'.hash('sha256', $bytes),
                    'size' => strlen($bytes),
                ];
            }, $this->files()),
        ];
    }

    /**
     * @return array<string, mixed>&array{name: string, description: string}
     */
    protected function parse(string $contents): array
    {
        if (preg_match('/\A(?:\xEF\xBB\xBF)?---\R(.*?)\R---(?:\R|\z)/s', $contents, $matches) !== 1) {
            throw $this->invalidFrontmatter('The SKILL.md file must start with a YAML frontmatter block.');
        }

        try {
            $frontmatter = Yaml::parse($matches[1], Yaml::PARSE_DATETIME | Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (ParseException $parseException) {
            throw $this->invalidFrontmatter($parseException->getMessage(), $parseException);
        }

        if (! is_array($frontmatter)) {
            throw $this->invalidFrontmatter('The frontmatter must be a YAML mapping.');
        }

        $frontmatter = $this->normalize($frontmatter);

        $validator = Validator::make($frontmatter, [
            'name' => ['required', 'string', 'max:64', 'regex:/\A[\p{Ll}\p{Lo}\p{N}]+(?:-[\p{Ll}\p{Lo}\p{N}]+)*\z/u'],
            'description' => ['required', 'string', 'max:1024'],
            'compatibility' => ['sometimes', 'string', 'max:500'],
            'metadata' => ['sometimes', 'array'],
            'metadata.*' => ['string'],
        ]);

        if ($validator->fails()) {
            throw $this->invalidFrontmatter(implode(' ', $validator->errors()->all()));
        }

        if ($frontmatter['name'] !== basename($this->directory())) {
            throw new InvalidArgumentException("The skill name [{$frontmatter['name']}] must match its directory [{$this->directory()}].");
        }

        /** @var array<string, mixed>&array{name: string, description: string} $frontmatter */
        return $frontmatter;
    }

    protected function normalize(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s') === '00:00:00' && $value->getOffset() === 0
                ? $value->format('Y-m-d')
                : $value->format(DateTimeInterface::ATOM);
        }

        return is_array($value) ? array_map($this->normalize(...), $value) : $value;
    }

    /**
     * @param  array{name: string}  $frontmatter
     */
    protected function uriFor(array $frontmatter): string
    {
        $uri = $this->resolveAttribute(Uri::class)->value ?? 'skill://'.rawurlencode($frontmatter['name']).'/SKILL.md';

        if (! str_ends_with($uri, '/SKILL.md') || rawurldecode(basename(substr($uri, 0, -strlen('/SKILL.md')))) !== $frontmatter['name']) {
            throw new InvalidArgumentException("The skill URI [{$uri}] must end with [/{$frontmatter['name']}/SKILL.md].");
        }

        return $uri;
    }

    protected function fileUri(string $uri, string $file): string
    {
        return substr($uri, 0, -strlen('SKILL.md')).implode('/', array_map(rawurlencode(...), explode('/', $file)));
    }

    /**
     * @param  array{name: string, description: string}  $frontmatter
     */
    protected function makeResource(array $frontmatter, string $file): SkillResource
    {
        $name = $frontmatter['name'];
        $uri = $this->fileUri($this->uriFor($frontmatter), $file);

        return $file === 'SKILL.md'
            ? new SkillResource($this, $file, $uri, $name, $name, $frontmatter['description'])
            : new SkillResource($this, $file, $uri, $file, $file, "The [{$file}] file of the [{$name}] skill.");
    }

    protected function invalidFrontmatter(string $reason, ?Throwable $previous = null): InvalidArgumentException
    {
        return new InvalidArgumentException(
            "The skill [{$this->directory()}] has an invalid SKILL.md frontmatter: {$reason}",
            previous: $previous,
        );
    }
}
