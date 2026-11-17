<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Laravel\Mcp\Exceptions\InvalidSkillException;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Concerns\ReadsAttributes;
use Laravel\Mcp\Server\Skills\Frontmatter;
use Laravel\Mcp\Server\Skills\SkillDirectory;
use Laravel\Mcp\Server\Skills\SkillResource;
use RuntimeException;
use Throwable;

/**
 * @implements Arrayable<string, mixed>
 */
abstract class Skill implements Arrayable
{
    use ReadsAttributes;

    protected ?SkillDirectory $directory = null;

    abstract public function path(): string;

    public function directory(): SkillDirectory
    {
        return $this->directory ??= new SkillDirectory($this->path());
    }

    public function uri(): string
    {
        return $this->resolveAttribute(Uri::class)->value
            ?? 'skill://'.rawurlencode($this->directory()->basename()).'/SKILL.md';
    }

    public function eligibleForRegistration(): bool
    {
        if (method_exists($this, 'shouldRegister')) {
            return (bool) Container::getInstance()->call([$this, 'shouldRegister']);
        }

        return true;
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
        return Frontmatter::parse($this->read('SKILL.md'));
    }

    /**
     * @return list<string>
     */
    public function files(): array
    {
        return $this->directory()->files();
    }

    public function read(string $file): string
    {
        return $this->directory()->read($file);
    }

    public function fileUri(string $file): string
    {
        return substr($this->uri(), 0, -strlen('SKILL.md')).implode('/', array_map(rawurlencode(...), explode('/', $file)));
    }

    /**
     * @return list<SkillResource>
     */
    public function resources(): array
    {
        [$files, $frontmatter] = $this->inspect();

        return array_map(fn (string $file): SkillResource => new SkillResource(
            $this,
            $file,
            $file === 'SKILL.md' ? $frontmatter['name'] : basename($file),
            $file === 'SKILL.md' ? $frontmatter['description'] : '',
        ), $files);
    }

    /**
     * @return array{uri: string, frontmatter: array<string, mixed>, resources: list<array{uri: string, digest: string, size: int}>}
     */
    public function toArray(): array
    {
        [$files] = $this->inspect();

        try {
            $contents = $this->read('SKILL.md');
            $frontmatter = Frontmatter::parse($contents);
            $resources = array_map(fn (string $file): array => [
                'uri' => $this->fileUri($file),
                ...($file === 'SKILL.md' ? SkillDirectory::hash($contents) : $this->directory()->digest($file)),
            ], $files);
        } catch (Throwable $throwable) {
            throw InvalidSkillException::for($this, $throwable);
        }

        return ['uri' => $this->uri(), 'frontmatter' => $frontmatter, 'resources' => $resources];
    }

    /**
     * @return array{0: list<string>, 1: array<string, mixed>&array{name: string, description: string}}
     */
    protected function inspect(): array
    {
        try {
            if (! is_file($this->directory()->root().DIRECTORY_SEPARATOR.'SKILL.md')) {
                throw new RuntimeException('A skill must contain a SKILL.md file at its root.');
            }

            $frontmatter = $this->frontmatter();
            $files = $this->files();

            if ($frontmatter['name'] !== $this->directory()->basename()) {
                throw new RuntimeException("The skill name [{$frontmatter['name']}] must match its directory name [{$this->directory()->basename()}].");
            }

            $this->ensureUriIsValid($frontmatter['name']);
            json_encode($frontmatter, JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            throw InvalidSkillException::for($this, $throwable);
        }

        return [$files, $frontmatter];
    }

    protected function ensureUriIsValid(string $name): void
    {
        $uri = $this->uri();

        if (preg_match('~\A[a-z][a-z0-9+.-]*://[^?#\s]+/SKILL\.md\z~i', $uri) !== 1 || rawurldecode(basename(substr($uri, 0, -strlen('/SKILL.md')))) !== $name) {
            throw new RuntimeException("The skill URI [{$uri}] must end with the skill name followed by /SKILL.md.");
        }

        foreach (explode('/', substr($uri, (int) strpos($uri, '://') + 3)) as $segment) {
            $decoded = rawurldecode($segment);

            if (in_array($decoded, ['', '.', '..'], true) || preg_match('/[\x00-\x1F\x7F\\\\\/]/', $decoded) === 1 || preg_match('/%(?![a-f0-9]{2})/i', $segment) === 1) {
                throw new RuntimeException("The skill URI [{$uri}] must contain valid, unambiguous path segments.");
            }
        }
    }
}
