<?php

declare(strict_types=1);

namespace Laravel\Mcp\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand(
    name: 'mcp:start',
    description: 'Start the MCP server for a given handle'
)]
class StartServerCommand extends Command
{
    public function handle(): int
    {
        $registrar = app('mcp');
        $handle = $this->argument('handle');
        $server = $registrar->getLocalServer($handle);

        if (! $server) {
            $this->error("MCP server with handle '{$handle}' not found.");

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
}
