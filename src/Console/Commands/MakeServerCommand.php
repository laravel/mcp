<?php

declare(strict_types=1);

namespace Laravel\Mcp\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'make:mcp-server',
    description: 'Create a new MCP server class'
)]
class MakeServerCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $type = 'Server';

    protected function getStub(): string
    {
        return file_exists($customPath = $this->laravel->basePath('stubs/server.stub'))
            ? $customPath
            : __DIR__.'/../../../stubs/server.stub';
    }

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return "{$rootNamespace}\\Mcp\\Servers";
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the server already exists'],
        ];
    }

    /**
     * @param  string  $name
     *
     * @throws FileNotFoundException
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $className = class_basename($name);

        $serverDisplayName = trim((string) preg_replace('/(?<!^)([A-Z])/', ' $1', $className));

        return str_replace('{{ serverDisplayName }}', $serverDisplayName, $stub);
    }
}
