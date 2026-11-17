<?php

declare(strict_types=1);

namespace Laravel\Mcp\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'make:mcp-skill',
    description: 'Create a new MCP skill class and SKILL.md file'
)]
class MakeSkillCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $type = 'Skill';

    public function handle(): ?bool
    {
        $result = parent::handle();

        if ($result === false) {
            return false;
        }

        $this->createSkillFile();

        return $result;
    }

    protected function buildClass($name): string
    {
        return str_replace('{{ path }}', $this->getSkillDirectory(), parent::buildClass($name));
    }

    protected function getStub(): string
    {
        return $this->resolveStub('mcp-skill.stub');
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return "{$rootNamespace}\\Mcp\\Skills";
    }

    protected function createSkillFile(): void
    {
        $skillPath = resource_path($this->getSkillDirectory().'/SKILL.md');

        if ($this->files->exists($skillPath) && ! $this->option('force')) {
            $this->components->warn("Skill file [{$skillPath}] already exists.");

            return;
        }

        $this->files->ensureDirectoryExists(dirname($skillPath));

        $this->files->put($skillPath, str_replace(
            ['{{ name }}', '{{ title }}'],
            [basename($this->getSkillDirectory()), Str::headline(class_basename($this->getNameInput()))],
            $this->files->get($this->resolveStub('mcp-skill.md.stub')),
        ));

        $this->components->info("Skill file [{$skillPath}] created successfully.");
    }

    protected function resolveStub(string $name): string
    {
        return file_exists($customPath = $this->laravel->basePath("stubs/{$name}"))
            ? $customPath
            : __DIR__."/../../../stubs/{$name}";
    }

    protected function getSkillDirectory(): string
    {
        return 'skills/'.collect(explode('/', str_replace('\\', '/', $this->getNameInput())))
            ->map(fn (string $segment): string => Str::kebab($segment))
            ->implode('/');
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the skill already exists'],
        ];
    }
}
