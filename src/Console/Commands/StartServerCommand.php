<?php

namespace Laravel\Mcp\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Mcp\Session\ArraySessionStore;
use Laravel\Mcp\Transport\Stdio;
use Laravel\Mcp\Transport\StdioTransport;

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
        $serverClass = $registrar->getServer($handle);

        if (! $serverClass) {
            $this->error("MCP server with handle '{$handle}' not found.");
            return 1;
        }

        $sessionStore = new ArraySessionStore();
        $server = new $serverClass($sessionStore);

        $transport = new StdioTransport(new Stdio());
        $server->connect($transport);

        $transport->run();
    }
}
