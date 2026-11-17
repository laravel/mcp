<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Concerns\ReadsAttributes;
use Laravel\Mcp\Server\Skills\SkillResource;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;

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

        return str_starts_with($path, '/') || preg_match('~\A[a-z]:[\\\\/]~i', $path) === 1 ? $path : resource_path($path);
    }

    public function uri(): string
    {
        return $this->uriFor($this->frontmatter());
    }

    public function eligibleForRegistration(): bool
    {
        return ! method_exists($this, 'shouldRegister') || (bool) Container::getInstance()->call([$this, 'shouldRegister']);
    }

    /**
     * @return array<string, mixed>&array{name: string, description: string}
     */
    public function frontmatter(): array
    {
        return $this->parse($this->read('SKILL.md'));
    }

    /**
     * @return list<string>
     */
    public function files(): array
    {
        $directory = $this->directory();

        $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            fn (SplFileInfo $file): bool => ! str_starts_with($file->getFilename(), '.') && ! $file->isLink(),
        ));

        $files = [];
        $size = 0;

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
                $size += $file->getSize();
            }
        }

        if (count($files) > self::MAX_FILES || $size > self::MAX_SIZE) {
            throw new RuntimeException("The skill [{$directory}] must not contain more than 512 files or 16 MiB.");
        }

        sort($files);

        return $files;
    }

    public function read(string $file): string
    {
        $directory = realpath($this->directory());
        $path = realpath($this->directory().DIRECTORY_SEPARATOR.$file);

        if ($directory === false || $path === false || ! str_starts_with($path, $directory.DIRECTORY_SEPARATOR) || ! is_file($path) || filesize($path) > self::MAX_SIZE) {
            throw new RuntimeException("The skill file [{$file}] is not readable.");
        }

        return (string) file_get_contents($path);
    }

    /**
     * @return list<SkillResource>
     */
    public function resources(): array
    {
        $frontmatter = $this->frontmatter();
        $uri = $this->uriFor($frontmatter);

        return array_map(fn (string $file): SkillResource => new SkillResource(
            $this,
            $file,
            $this->fileUri($uri, $file),
            $file === 'SKILL.md' ? $frontmatter['name'] : basename($file),
            $file === 'SKILL.md' ? $frontmatter['description'] : '',
        ), $this->files());
    }

    /**
     * @return array{uri: string, frontmatter: array<string, mixed>, resources: list<array{uri: string, digest: string, size: int}>}
     */
    public function toArray(): array
    {
        $contents = $this->read('SKILL.md');
        $frontmatter = $this->parse($contents);
        $uri = $this->uriFor($frontmatter);

        return [
            'uri' => $uri,
            'frontmatter' => $frontmatter,
            'resources' => array_map(function (string $file) use ($contents, $uri): array {
                $bytes = $file === 'SKILL.md' ? $contents : $this->read($file);

                return ['uri' => $this->fileUri($uri, $file), 'digest' => 'sha256:'.hash('sha256', $bytes), 'size' => strlen($bytes)];
            }, $this->files()),
        ];
    }

    /**
     * @return array<string, mixed>&array{name: string, description: string}
     */
    protected function parse(string $contents): array
    {
        $frontmatter = preg_match('/\A(?:\xEF\xBB\xBF)?---\R(.*?)\R---(?:\R|\z)/s', $contents, $matches) === 1
            ? Yaml::parse($matches[1])
            : null;

        if (! is_array($frontmatter)
            || ! is_string($frontmatter['name'] ?? null)
            || ! is_string($frontmatter['description'] ?? null)
            || mb_strlen($frontmatter['name']) > 64
            || preg_match('/\A[\p{Ll}\p{Lo}\p{N}]+(?:-[\p{Ll}\p{Lo}\p{N}]+)*\z/u', $frontmatter['name']) !== 1) {
            throw new RuntimeException("The skill [{$this->directory()}] must have a SKILL.md with a valid [name] and a [description] in its frontmatter.");
        }

        /** @var array<string, mixed>&array{name: string, description: string} $frontmatter */
        return $frontmatter;
    }

    /**
     * @param  array{name: string}  $frontmatter
     */
    protected function uriFor(array $frontmatter): string
    {
        return $this->resolveAttribute(Uri::class)->value ?? 'skill://'.rawurlencode($frontmatter['name']).'/SKILL.md';
    }

    protected function fileUri(string $uri, string $file): string
    {
        return substr($uri, 0, -strlen('SKILL.md')).implode('/', array_map(rawurlencode(...), explode('/', $file)));
    }
}
