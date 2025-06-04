<?php

namespace Laravel\Mcp\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Mcp\Session\Session;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Carbon;

class PruneSessionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:prune-sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old MCP sessions from the database.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $expirationMinutes = Config::get('mcp.session.expiration');

        if (is_null($expirationMinutes)) {
            $this->error('MCP session expiration is not configured. Please set mcp.session.expiration in your config.');
            return Command::FAILURE;
        }

        if (! is_numeric($expirationMinutes) || $expirationMinutes <= 0) {
            $this->error('MCP session expiration value must be a positive number of minutes.');
            return Command::FAILURE;
        }

        $cutOff = Carbon::now()->subMinutes($expirationMinutes);

        $count = Session::where('created_at', '<=', $cutOff)->delete();

        $this->info("Successfully pruned {$count} MCP session(s) older than {$cutOff->toDateTimeString()}.");

        return Command::SUCCESS;
    }
}
