<?php

declare(strict_types=1);

namespace Laravel\Mcp\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'make:mcp-ui-resource',
    description: 'Create a new MCP UI resource class'
)]
class MakeUiResourceCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $type = 'UiResource';

    public function handle(): ?bool
    {
        $result = parent::handle();

        if ($result === false) {
            return false;
        }

        $this->createBladeView();

        return $result;
    }

    protected function buildClass($name): string
    {
        return str_replace(
            '{{ view }}',
            'mcp.'.$this->getKebabName(),
            parent::buildClass($name),
        );
    }

    protected function getStub(): string
    {
        return file_exists($customPath = $this->laravel->basePath('stubs/mcp-ui-resource.stub'))
            ? $customPath
            : __DIR__.'/../../../stubs/mcp-ui-resource.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return "{$rootNamespace}\\Mcp\\Resources";
    }

    protected function createBladeView(): void
    {
        $viewPath = $this->getViewPath();

        if ($this->files->exists($viewPath) && ! $this->option('force')) {
            $this->components->warn("View [{$viewPath}] already exists.");

            return;
        }

        $this->files->ensureDirectoryExists(dirname($viewPath));

        $this->files->put($viewPath, $this->files->get($this->getViewStub()));

        $this->components->info("View [{$viewPath}] created successfully.");
    }

    protected function getViewStub(): string
    {
        return file_exists($customPath = $this->laravel->basePath('stubs/mcp-ui-resource.view.stub'))
            ? $customPath
            : __DIR__.'/../../../stubs/mcp-ui-resource.view.stub';
    }

    protected function getViewPath(): string
    {
        return resource_path('views/mcp/'.$this->getKebabName().'.blade.php');
    }

    protected function getKebabName(): string
    {
        return Str::kebab(class_basename($this->getNameInput()));
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the resource already exists'],
        ];
    }
}
