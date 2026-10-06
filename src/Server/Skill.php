<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Skills\SkillResource;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use stdClass;
use Symfony\Component\Yaml\Yaml;

class Skill extends Primitive
{
    public const MAX_RESOURCES = 512;

    public const MAX_SIZE = 16_777_216;

    protected string $uri = '';

    protected string $path = '';

    public static function fromDirectory(string $directory, string $uri = ''): self
    {
        $skill = new self;
        $skill->path = $directory;
        $skill->uri = $uri;

        return $skill;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function uri(): string
    {
        return $this->resolveAttribute(Uri::class)->value
            ?? ($this->uri !== '' ? $this->uri : 'skill://'.rawurlencode(basename($this->directory())).'/SKILL.md');
    }

    public function name(): string
    {
        return $this->frontmatter()['name'];
    }

    public function description(): string
    {
        return $this->frontmatter()['description'];
    }

    /**
     * @return array<string, mixed>&array{name: string, description: string}
     */
    public function frontmatter(): array
    {
        return $this->parseFrontmatter($this->read('SKILL.md'));
    }

    /**
     * @return array<int, SkillResource>
     */
    public function resources(): array
    {
        $frontmatter = $this->frontmatter();

        return array_map(fn (string $file): SkillResource => new SkillResource(
            $this,
            $file,
            $file === 'SKILL.md' ? $frontmatter['name'] : basename($file),
            $file === 'SKILL.md' ? $frontmatter['description'] : '',
        ), $this->files());
    }

    /**
     * Read only regular files beneath the configured skill directory.
     */
    public function read(string $file): string
    {
        $directory = $this->directory();
        $segments = explode('/', $file);
        $path = $directory;

        foreach ($segments as $segment) {
            if (in_array($segment, ['', '.', '..'], true) || strpbrk($segment, "\\\0") !== false) {
                throw new RuntimeException('Invalid skill file path.');
            }

            $path .= DIRECTORY_SEPARATOR.$segment;
            clearstatcache(true, $path);

            if (is_link($path)) {
                throw new RuntimeException('Skill files must not contain symbolic links.');
            }
        }

        $realPath = realpath($path);

        if ($realPath === false || ! str_starts_with($realPath, $directory.DIRECTORY_SEPARATOR) || ! is_file($realPath) || ! is_readable($realPath)) {
            throw new RuntimeException('The skill file is not a readable regular file.');
        }

        $contents = file_get_contents($realPath, length: self::MAX_SIZE + 1);

        if ($contents === false || strlen($contents) > self::MAX_SIZE) {
            throw new RuntimeException('Skill files must not exceed 16 MiB.');
        }

        return $contents;
    }

    /**
     * @return array{uri: string}
     */
    public function toMethodCall(): array
    {
        return ['uri' => $this->uri()];
    }

    /**
     * @return array{uri: string, frontmatter: array<string, mixed>, resources: list<array{uri: string, digest: string, size: int}>}
     */
    public function toArray(): array
    {
        $contents = $this->read('SKILL.md');
        $frontmatter = $this->parseFrontmatter($contents);
        $resources = [];
        $size = 0;

        foreach ($this->files() as $file) {
            $bytes = $file === 'SKILL.md' ? $contents : $this->read($file);
            $size += strlen($bytes);

            if ($size > self::MAX_SIZE) {
                throw new RuntimeException('Skills must not exceed 16 MiB in total.');
            }

            $resources[] = [
                'uri' => $this->fileUri($file),
                'digest' => 'sha256:'.hash('sha256', $bytes),
                'size' => strlen($bytes),
            ];
        }

        return ['uri' => $this->uri(), 'frontmatter' => $frontmatter, 'resources' => $resources];
    }

    public function fileUri(string $file): string
    {
        return substr($this->uri(), 0, -strlen('SKILL.md')).implode('/', array_map(rawurlencode(...), explode('/', $file)));
    }

    protected function directory(): string
    {
        $path = rtrim($this->path(), '/\\');

        if ($path === '') {
            throw new RuntimeException('The skill path must not be empty.');
        }

        clearstatcache(true, $path);
        $directory = realpath($path);

        if ($directory === false || ! is_dir($directory) || is_link($path)) {
            throw new RuntimeException('The skill path must be a directory, not a symbolic link.');
        }

        return $directory;
    }

    /**
     * @return list<string>
     */
    protected function files(): array
    {
        $directory = $this->directory();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        $files = [];

        foreach ($iterator as $file) {
            if ($file->isLink() || (! $file->isDir() && ! $file->isFile())) {
                throw new RuntimeException('Skill directories may contain only regular files and directories.');
            }

            if ($file->isDir()) {
                continue;
            }

            $files[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($directory) + 1));

            if (count($files) > self::MAX_RESOURCES) {
                throw new RuntimeException('Skills must not contain more than 512 files.');
            }
        }

        sort($files, SORT_STRING);

        if (! in_array('SKILL.md', $files, true)) {
            throw new RuntimeException('A skill must contain SKILL.md at its root.');
        }

        return $files;
    }

    /**
     * @return array<string, mixed>&array{name: string, description: string}
     */
    protected function parseFrontmatter(string $contents): array
    {
        if (! mb_check_encoding($contents, 'UTF-8') || preg_match('/\A---\r?\n(.*?)^---[ \t]*(?:\r?\n|\z)/ms', $contents, $matches) !== 1) {
            throw new RuntimeException('SKILL.md must begin with YAML frontmatter and contain UTF-8 text.');
        }

        $frontmatter = Yaml::parse($matches[1], Yaml::PARSE_OBJECT_FOR_MAP | Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_DATETIME);

        if (! $frontmatter instanceof stdClass) {
            throw new RuntimeException('Skill frontmatter must be a YAML mapping.');
        }

        $this->validateFrontmatterValue($frontmatter);

        $frontmatter = get_object_vars($frontmatter);

        if (! is_string($frontmatter['name'] ?? null) || ! is_string($frontmatter['description'] ?? null)) {
            throw new RuntimeException('Skill frontmatter must contain a name and description.');
        }

        if (mb_strlen($frontmatter['name']) > 64 || preg_match('/\A[\p{Ll}\p{Lo}\p{N}]+(?:-[\p{Ll}\p{Lo}\p{N}]+)*\z/u', $frontmatter['name']) !== 1 || $frontmatter['name'] !== basename($this->directory())) {
            throw new RuntimeException('The skill name must follow the Agent Skills naming rules and match its directory.');
        }

        if (trim($frontmatter['description']) === '' || mb_strlen($frontmatter['description']) > 1024) {
            throw new RuntimeException('Skill descriptions must contain between 1 and 1024 characters.');
        }

        foreach (['license', 'compatibility', 'allowed-tools'] as $field) {
            if (array_key_exists($field, $frontmatter) && ! is_string($frontmatter[$field])) {
                throw new RuntimeException("The skill [{$field}] field must be a string.");
            }
        }

        if (isset($frontmatter['compatibility']) && (trim($frontmatter['compatibility']) === '' || mb_strlen($frontmatter['compatibility']) > 500)) {
            throw new RuntimeException('Skill compatibility must contain between 1 and 500 characters.');
        }

        if (array_key_exists('metadata', $frontmatter)) {
            if (! $frontmatter['metadata'] instanceof stdClass) {
                throw new RuntimeException('Skill metadata must be a mapping of strings to strings.');
            }

            foreach (get_object_vars($frontmatter['metadata']) as $value) {
                if (! is_string($value)) {
                    throw new RuntimeException('Skill metadata must be a mapping of strings to strings.');
                }
            }
        }

        $uri = $this->uri();

        if (preg_match('~\A[a-z][a-z0-9+.-]*://[^?#\s]+/SKILL\.md\z~i', $uri) !== 1 || rawurldecode(basename(substr($uri, 0, -strlen('/SKILL.md')))) !== $frontmatter['name']) {
            throw new RuntimeException('The skill URI must end with the skill name followed by /SKILL.md.');
        }

        foreach (explode('/', substr($uri, strpos($uri, '://') + 3)) as $segment) {
            $decoded = rawurldecode($segment);

            if (in_array($decoded, ['', '.', '..'], true) || preg_match('/[\\x00-\\x1F\\x7F\\\\\\/]/', $decoded) === 1 || preg_match('/%(?![a-f0-9]{2})/i', $segment) === 1) {
                throw new RuntimeException('The skill URI must contain valid, unambiguous path segments.');
            }
        }

        // Reject YAML values that cannot be represented by the JSON transport.
        json_encode($frontmatter, JSON_THROW_ON_ERROR);

        return $frontmatter;
    }

    private function validateFrontmatterValue(mixed $value): void
    {
        if (is_object($value) && ! $value instanceof stdClass) {
            throw new RuntimeException('Skill frontmatter must use JSON-compatible values. Quote dates and timestamps to preserve them as strings.');
        }

        if (is_array($value) || $value instanceof stdClass) {
            foreach ((array) $value as $child) {
                $this->validateFrontmatterValue($child);
            }
        }
    }
}
