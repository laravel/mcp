<?php

namespace Laravel\Mcp\Console\Commands;

use Illuminate\Console\Command;

class StartServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:start {handle : The handle of the MCP server to start.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the MCP server for a given handle';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $registrar = app('mcp');
        $handle = $this->argument('handle');
        $server = $registrar->getCliServer($handle);

        if (! $server) {
            $this->error("MCP server with handle '{$handle}' not found.");
            return Command::FAILURE;
        }

        $server();
    }
}
