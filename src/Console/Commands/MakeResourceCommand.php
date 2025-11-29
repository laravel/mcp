<?php

declare(strict_types=1);

namespace Laravel\Mcp\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'make:mcp-resource',
    description: 'Create a new MCP resource class'
)]
class MakeResourceCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $type = 'Resource';

    protected function getStub(): string
    {
        $stubName = $this->option('template') ? 'resource-template.stub' : 'resource.stub';
        $customStub = $this->laravel->basePath("stubs/{$stubName}");

        if (file_exists($customStub)) {
            return $customStub;
        }

        return __DIR__."/../../../stubs/{$stubName}";
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return "{$rootNamespace}\\Mcp\\Resources";
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the resource already exists'],
            ['template', 't', InputOption::VALUE_NONE, 'Create the ResourceTemplate class'],
        ];
    }
}
