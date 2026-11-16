<?php

declare(strict_types=1);

namespace Laravel\Mcp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Skills\Frontmatter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'make:mcp-skill',
    description: 'Create a new MCP skill directory or class'
)]
class MakeSkillCommand extends Command
{
    public function handle(Filesystem $files): int
    {
        $argument = $this->argument('name');
        $name = Str::kebab(is_string($argument) ? $argument : '');

        if (mb_strlen($name) > 64 || preg_match(Frontmatter::NAME_PATTERN, $name) !== 1) {
            $this->components->error("The skill name [{$name}] must be at most 64 lowercase letters, numbers, and single hyphens.");

            return static::FAILURE;
        }

        $class = Str::studly($name);
        $targets = array_filter([
            resource_path("skills/{$name}/SKILL.md") => 'mcp-skill.stub',
            app_path("Mcp/Skills/{$class}.php") => $this->option('class') ? 'mcp-skill.class.stub' : null,
        ]);

        foreach (array_keys($targets) as $path) {
            if ($files->exists($path) && ! $this->option('force')) {
                $this->components->error("Skill [{$path}] already exists.");

                return static::FAILURE;
            }
        }

        $description = $this->option('description');
        $description = is_string($description) && trim($description) !== ''
            ? $description
            : 'Describe when an agent should use this skill.';

        foreach ($targets as $path => $stub) {
            $files->ensureDirectoryExists(dirname($path));
            $files->put($path, str_replace(
                ['{{ namespace }}', '{{ class }}', '{{ name }}', '{{ description }}', '{{ title }}'],
                [$this->laravel->getNamespace().'Mcp\\Skills', $class, $name, Yaml::dump($description), Str::headline($name)],
                $files->get($this->getStub($stub)),
            ));

            $this->components->info(sprintf('Skill [%s] created successfully.', $path));
        }

        return static::SUCCESS;
    }

    protected function getStub(string $stub): string
    {
        return file_exists($customPath = $this->laravel->basePath("stubs/{$stub}"))
            ? $customPath
            : __DIR__."/../../../stubs/{$stub}";
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the skill'],
        ];
    }

    /**
     * @return array<int, array<int, string|int|null>>
     */
    protected function getOptions(): array
    {
        return [
            ['description', 'd', InputOption::VALUE_REQUIRED, 'When an agent should use the skill'],
            ['class', 'c', InputOption::VALUE_NONE, 'Also create a skill class in app/Mcp/Skills'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the skill if it already exists'],
        ];
    }
}
