<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Skills;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

class SkillDirectory
{
    public const MAX_FILES = 512;

    public const MAX_SIZE = 16_777_216;

    /**
     * @var array<string, array{digest: string, size: int}>
     */
    protected static array $digests = [];

    public readonly string $path;

    public function __construct(string $path)
    {
        $this->path = static::resolve($path);
    }

    public static function resolve(string $path): string
    {
        return $path === '' || preg_match('~\A(?:[a-z][a-z0-9+.-]*://|[a-z]:[\\\\/]|[\\\\/])~i', $path) === 1
            ? $path
            : resource_path($path);
    }

    public function root(): string
    {
        $path = rtrim($this->path, '/\\');

        if ($path === '') {
            throw new RuntimeException('The skill directory path must not be empty.');
        }

        clearstatcache(true, $path);
        $root = realpath($path);

        if ($root === false || ! is_dir($root) || is_link($path)) {
            throw new RuntimeException("The skill directory [{$this->path}] must exist and must not be a symbolic link.");
        }

        return $root;
    }

    public function basename(): string
    {
        return basename($this->root());
    }

    /**
     * @return list<string>
     */
    public function files(): array
    {
        $root = $this->root();
        $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            fn (SplFileInfo $file): bool => ! str_starts_with($file->getFilename(), '.'),
        ));
        $files = [];
        $size = 0;

        foreach ($iterator as $file) {
            assert($file instanceof SplFileInfo);

            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));

            if ($file->isLink() || ! $file->isFile()) {
                throw new RuntimeException("The skill file [{$relative}] must be a regular file, not a symbolic link or special file.");
            }

            if (str_contains($relative, '\\') || ! $file->isReadable()) {
                throw new RuntimeException("The skill file [{$relative}] must be readable and must not contain backslashes in its path.");
            }

            $files[] = $relative;
            $size += $file->getSize();

            if (count($files) > self::MAX_FILES) {
                throw new RuntimeException('A skill must not contain more than '.self::MAX_FILES.' files.');
            }

            if ($size > self::MAX_SIZE) {
                throw new RuntimeException('A skill must not exceed 16 MiB in total.');
            }
        }

        sort($files, SORT_STRING);

        if (! in_array('SKILL.md', $files, true)) {
            throw new RuntimeException('A skill must contain a SKILL.md file at its root.');
        }

        return $files;
    }

    public function read(string $file): string
    {
        $root = $this->root();
        $path = $root;

        foreach (explode('/', $file) as $segment) {
            if (in_array($segment, ['', '.', '..'], true) || str_starts_with($segment, '.') || strpbrk($segment, "\\\0") !== false) {
                throw new RuntimeException('The skill file path is invalid.');
            }

            $path .= DIRECTORY_SEPARATOR.$segment;
            clearstatcache(true, $path);

            if (is_link($path)) {
                throw new RuntimeException('Skill files must not be symbolic links.');
            }
        }

        $realPath = realpath($path);

        if ($realPath === false || ! str_starts_with($realPath, $root.DIRECTORY_SEPARATOR) || ! is_file($realPath) || ! is_readable($realPath)) {
            throw new RuntimeException('The skill file is not a readable regular file.');
        }

        $contents = file_get_contents($realPath, length: self::MAX_SIZE + 1);

        if ($contents === false || strlen($contents) > self::MAX_SIZE) {
            throw new RuntimeException('Skill files must not exceed 16 MiB.');
        }

        return $contents;
    }

    /**
     * @return array{digest: string, size: int}
     */
    public function digest(string $file): array
    {
        $path = $this->root().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file);
        clearstatcache(true, $path);
        $stat = @stat($path);
        $key = $stat === false ? null : implode('|', [$path, $stat['dev'], $stat['ino'], $stat['size'], $stat['mtime'], $stat['ctime']]);

        if ($key !== null && isset(static::$digests[$key])) {
            return static::$digests[$key];
        }

        $digest = static::hash($this->read($file));

        if ($key !== null && $digest['size'] === $stat['size'] && max($stat['mtime'], $stat['ctime']) < time() - 1) {
            static::$digests[$key] = $digest;
        }

        return $digest;
    }

    /**
     * @return array{digest: string, size: int}
     */
    public static function hash(string $contents): array
    {
        return ['digest' => 'sha256:'.hash('sha256', $contents), 'size' => strlen($contents)];
    }

    public static function flushDigests(): void
    {
        static::$digests = [];
    }
}
