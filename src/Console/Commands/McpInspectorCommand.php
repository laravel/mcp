<?php

namespace Laravel\Mcp\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class McpInspectorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:inspector {handle : The handle of the MCP server to inspect.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Open the MCP inspector tool to debug and test MCP servers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $handle = $this->argument('handle');

        $this->info("Starting MCP Inspector for server: mcp:{$handle}");

        $currentDir = getcwd();
        $command = [
            'npx',
            '@modelcontextprotocol/inspector',
            'php',
            $currentDir . '/artisan',
            "mcp:start {$handle}"
        ];

        $this->line('Running: ' . implode(' ', $command));

        $process = new Process($command);
        $process->setTimeout(null);

        try {
            $process->mustRun(function ($type, $buffer) {
                echo $buffer;
            });
        } catch (\Exception $e) {
            $this->error('Failed to start MCP Inspector: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
