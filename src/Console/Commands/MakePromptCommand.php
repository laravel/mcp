<?php

declare(strict_types=1);

namespace Laravel\Mcp\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'make:mcp-prompt',
    description: 'Create a new MCP prompt class'
)]
class MakePromptCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $type = 'Prompt';

    protected function getStub(): string
    {
        return file_exists($customPath = $this->laravel->basePath('stubs/prompt.stub'))
            ? $customPath
            : __DIR__.'/../../../stubs/prompt.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return "{$rootNamespace}\\Mcp\\Prompts";
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the prompt already exists'],
        ];
    }
}
