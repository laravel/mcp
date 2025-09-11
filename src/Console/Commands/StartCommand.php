<?php

declare(strict_types=1);

namespace Laravel\Mcp\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Mcp\Server\Registrar;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand(
    name: 'mcp:start',
    description: 'Start the MCP Server for a given handle.'
)]
class StartCommand extends Command
{
    public function handle(Registrar $registrar): int
    {
        $handle = $this->argument('handle');

        assert(is_string($handle));

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
}
