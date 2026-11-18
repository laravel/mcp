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
        if (strlen($this->skillName()) > 64) {
            $this->fail("The skill name [{$this->skillName()}] must not be longer than 64 characters.");
        }

        $result = parent::handle();

        if ($result === false) {
            return false;
        }

        $this->createSkillFile();

        return $result;
    }

    protected function createSkillFile(): void
    {
        $name = $this->skillName();
        $path = resource_path("skills/{$name}/SKILL.md");

        if ($this->files->exists($path) && ! $this->option('force')) {
            $this->components->warn("Skill file [{$path}] already exists.");

            return;
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, str_replace(
            ['{{ name }}', '{{ title }}'],
            [$name, Str::headline(class_basename($this->getNameInput()))],
            $this->files->get($this->resolveStub('mcp-skill.md.stub')),
        ));

        $this->components->info("Skill file [{$path}] created successfully.");
    }

    protected function getStub(): string
    {
        return $this->resolveStub('mcp-skill.stub');
    }

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return "{$rootNamespace}\\Mcp\\Skills";
    }

    /**
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        return str_replace('{{ path }}', "skills/{$this->skillName()}", parent::buildClass($name));
    }

    protected function skillName(): string
    {
        return Str::slug(Str::kebab(class_basename($this->getNameInput())));
    }

    protected function resolveStub(string $stub): string
    {
        return file_exists($customPath = $this->laravel->basePath("stubs/{$stub}"))
            ? $customPath
            : __DIR__."/../../../stubs/{$stub}";
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class and SKILL.md even if the skill already exists'],
        ];
    }
}
