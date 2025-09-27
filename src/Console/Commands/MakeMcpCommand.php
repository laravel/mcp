<?php

declare(strict_types=1);

namespace Laravel\Mcp\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputOption;

abstract class MakeMcpCommand extends GeneratorCommand
{
    protected string $lowerType;

    public function __construct(Filesystem $files)
    {
        $this->lowerType = strtolower($this->type);
        $this->name = 'make:mcp-'.$this->lowerType;
        $this->description = 'Create a new MCP '.$this->type.' class';
        parent::__construct($files);
    }

    protected function getStub(): string
    {
        $filename = $this->lowerType.'.stub';
        $custom = $this->laravel->basePath('stubs/'.$filename);
        if (file_exists($custom)) {
            return $custom;
        }

        $dir = __DIR__.'/../../../stubs/';
        if ($this->option('quickstart')) {
            $dir .= 'quickstart/';
        }

        return $dir.$filename;
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return "{$rootNamespace}\\Mcp\\{$this->type}s";
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the '.$this->lowerType.' already exists'],
            ['quickstart', 'qs', InputOption::VALUE_NONE, 'Create the quickstart version'],
        ];
    }
}
