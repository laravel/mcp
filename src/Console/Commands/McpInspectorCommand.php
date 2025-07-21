<?php

namespace Laravel\Mcp\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Process\Process;

use function Illuminate\Support\php_binary;

#[AsCommand(
    name: 'mcp:inspector',
    description: 'Open the MCP inspector tool to debug and test MCP servers'
)]
class McpInspectorCommand extends Command
{
    /**
     * Start the MCP Inspector tool.
     */
    public function handle()
    {
        $handle = $this->argument('handle');

        $this->info("Starting the MCP Inspector for server: {$handle}");

        $currentDir = getcwd();
        $command = [
            'npx',
            '@modelcontextprotocol/inspector',
            php_binary(),
            $currentDir.'/artisan',
            "mcp:start {$handle}",
        ];

        $mcpCommand = php_binary();
        $mcpArguments = $currentDir.'/artisan '.'mcp:start '.$handle;

        $process = new Process($command);
        $process->setTimeout(null);

        try {
            $this->info(sprintf('Command: %s%sArguments: %s%s%s', $mcpCommand, PHP_EOL, $mcpArguments, PHP_EOL, PHP_EOL));
            $process->mustRun(function ($type, $buffer) {
                echo $buffer;
            });
        } catch (Exception $e) {
            $this->error('Failed to start MCP Inspector: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['handle', InputArgument::REQUIRED, 'The handle of the MCP server to inspect.'],
        ];
    }
}
