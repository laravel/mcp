<?php

declare(strict_types=1);

namespace Laravel\Mcp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Registrar;
use Laravel\Mcp\Server\Transport\StdioTransport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'mcp:start',
    description: 'Start the MCP Server for a given handle'
)]
class StartCommand extends Command
{
    public function handle(Registrar $registrar): int
    {
        $handle = $this->argument('handle');

        assert(is_string($handle));

        /** @var array<int, string> $toolClasses */
        $toolClasses = (array) $this->option('tool');

        /** @var array<int, string> $tags */
        $tags = (array) $this->option('tag');

        foreach ($toolClasses as $class) {
            if ($class === '') {
                $this->components->error('Each --tool value must be a non-empty fully-qualified class name.');

                return static::FAILURE;
            }
        }

        foreach ($tags as $tag) {
            if ($tag === '') {
                $this->components->error('Each --tag value must be a non-empty tag name.');

                return static::FAILURE;
            }
        }

        if ($toolClasses !== [] || $tags !== []) {
            $serverClass = $registrar->getLocalServerClass($handle);

            if ($serverClass === null) {
                $this->components->error("MCP Server with name [{$handle}] not found. Did you register it using [Mcp::local()]?");

                return static::FAILURE;
            }

            $transport = new StdioTransport;

            try {
                /** @var Server $server */
                $server = Container::getInstance()->make($serverClass, ['transport' => $transport]);
            } catch (BindingResolutionException $e) {
                $this->components->error("MCP Server [{$serverClass}] could not be resolved: {$e->getMessage()}");

                return static::FAILURE;
            }

            $server->withToolFilter($toolClasses, $tags);
            $server->start();
            $transport->run();

            return static::SUCCESS;
        }

        $server = $registrar->getLocalServer($handle);

        if ($server === null) {
            $this->components->error("MCP Server with name [{$handle}] not found. Did you register it using [Mcp::local()]?");

            return static::FAILURE;
        }

        $server();

        return static::SUCCESS;
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getArguments(): array
    {
        return [
            ['handle', InputArgument::REQUIRED, 'The handle of the MCP server to start.'],
        ];
    }

    /**
     * @return array<int, array<int, int|string|null>>
     */
    protected function getOptions(): array
    {
        return [
            ['tool', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Only serve tools whose class matches one of these FQCNs (union with --tag).'],
            ['tag', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Only serve tools carrying one of these tags (union with --tool).'],
        ];
    }
}
