<?php

declare(strict_types=1);

namespace Laravel\Mcp\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'make:mcp-resource-template',
    description: 'Create a new MCP resource template class'
)]
class MakeResourceTemplateCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $type = 'ResourceTemplate';

    protected function getStub(): string
    {
        return file_exists($customPath = $this->laravel->basePath('stubs/resource-template.stub'))
            ? $customPath
            : __DIR__.'/../../../stubs/resource-template.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return "{$rootNamespace}\\Mcp\\ResourceTemplates";
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the resource template already exists'],
        ];
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        return str_replace(
            '{{ classKebab }}',
            Str::kebab(class_basename($name)),
            $stub
        );
    }
}
