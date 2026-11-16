<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Laravel\Mcp\Exceptions\InvalidSkillException;
use Laravel\Mcp\Server\Skills\Frontmatter;
use Laravel\Mcp\Server\Skills\SkillDirectory;
use Laravel\Mcp\Server\Skills\SkillResource;
use RuntimeException;
use Stringable;
use Throwable;

/**
 * @implements Arrayable<string, mixed>
 */
class Skill implements Arrayable
{
    protected string $name = '';

    protected string $description = '';

    protected View|Htmlable|Stringable|string $instructions = '';

    /**
     * @var array<string, mixed>
     */
    protected array $frontmatter = [];

    protected ?string $uri = null;

    protected ?string $directory = null;

    protected ?SkillDirectory $resolvedDirectory = null;

    /**
     * @param  array<string, mixed>|null  $frontmatter
     */
    public function __construct(
        ?string $name = null,
        ?string $description = null,
        View|Htmlable|Stringable|string|null $instructions = null,
        ?array $frontmatter = null,
        ?string $uri = null,
    ) {
        $this->name = $name ?? $this->name;
        $this->description = $description ?? $this->description;
        $this->instructions = $instructions ?? $this->instructions;
        $this->frontmatter = $frontmatter ?? $this->frontmatter;
        $this->uri = $uri ?? $this->uri;
    }

    public static function fromDirectory(string $directory, ?string $uri = null): static
    {
        $skill = new static(uri: $uri);
        $skill->directory = $directory;

        return $skill;
    }

    public function directory(): ?SkillDirectory
    {
        if ($this->directory === null) {
            return null;
        }

        return $this->resolvedDirectory ??= new SkillDirectory($this->directory);
    }

    public function identifier(): string
    {
        return match (true) {
            $this->directory() instanceof SkillDirectory => $this->directory()->path,
            static::class !== self::class => static::class,
            default => $this->name,
        };
    }

    public function uri(): string
    {
        return $this->uri ?? 'skill://'.rawurlencode($this->directory()?->basename() ?? $this->name).'/SKILL.md';
    }

    public function instructions(): View|Htmlable|Stringable|string
    {
        return $this->instructions;
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
        return $this->directory()?->files() ?? ['SKILL.md'];
    }

    public function read(string $file): string
    {
        if ($this->directory() instanceof SkillDirectory) {
            return $this->directory()->read($file);
        }

        if ($file !== 'SKILL.md') {
            throw new RuntimeException("The skill file [{$file}] does not exist.");
        }

        return Frontmatter::render([
            ...$this->frontmatter,
            'name' => $this->name,
            'description' => $this->description,
        ], $this->renderInstructions());
    }

    public function fileUri(string $file): string
    {
        return substr($this->uri(), 0, -strlen('SKILL.md')).implode('/', array_map(rawurlencode(...), explode('/', $file)));
    }

    public function validate(): void
    {
        $this->inspect();
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
        $contents = $this->read('SKILL.md');
        $resources = [];
        $total = 0;

        foreach ($files as $file) {
            $digest = $file === 'SKILL.md'
                ? ['digest' => 'sha256:'.hash('sha256', $contents), 'size' => strlen($contents)]
                : $this->digest($file);

            $total += $digest['size'];

            if ($total > SkillDirectory::MAX_SIZE) {
                throw InvalidSkillException::for($this, new RuntimeException('A skill must not exceed 16 MiB in total.'));
            }

            $resources[] = ['uri' => $this->fileUri($file), ...$digest];
        }

        return ['uri' => $this->uri(), 'frontmatter' => Frontmatter::parse($contents), 'resources' => $resources];
    }

    /**
     * @return array{digest: string, size: int}
     */
    protected function digest(string $file): array
    {
        if ($this->directory() instanceof SkillDirectory) {
            return $this->directory()->digest($file);
        }

        $contents = $this->read($file);

        return ['digest' => 'sha256:'.hash('sha256', $contents), 'size' => strlen($contents)];
    }

    /**
     * @return array{0: list<string>, 1: array<string, mixed>&array{name: string, description: string}}
     */
    protected function inspect(): array
    {
        try {
            if ($this->directory() instanceof SkillDirectory && ! is_file($this->directory()->root().DIRECTORY_SEPARATOR.'SKILL.md')) {
                throw new RuntimeException('A skill must contain a SKILL.md file at its root.');
            }

            $frontmatter = $this->frontmatter();
            $files = $this->files();

            if ($this->directory() instanceof SkillDirectory && $frontmatter['name'] !== $this->directory()->basename()) {
                throw new RuntimeException("The skill name [{$frontmatter['name']}] must match its directory name [{$this->directory()->basename()}].");
            }

            if (! $this->directory() instanceof SkillDirectory && $frontmatter['name'] !== $this->name) {
                throw new RuntimeException("The skill name [{$this->name}] could not be represented in its frontmatter.");
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

    protected function renderInstructions(): string
    {
        $instructions = $this->instructions();

        return match (true) {
            $instructions instanceof View => $instructions->render(),
            $instructions instanceof Htmlable => $instructions->toHtml(),
            default => (string) $instructions,
        };
    }
}
