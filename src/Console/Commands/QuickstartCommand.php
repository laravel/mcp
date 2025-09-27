<?php

declare(strict_types=1);

namespace Laravel\Mcp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'mcp:quickstart',
    description: 'Quickly setup MCP fundamentals'
)]
class QuickstartCommand extends Command
{
    public function handle(): void
    {
        $appName = config('app.name', 'Quickstart');
        $sanitized = trim((string) preg_replace('/[^a-zA-Z \-]/', '', (string) $appName));
        $serverName = $sanitized === '' ? 'Quickstart' : Str::studly($sanitized);

        $this->callSilently('vendor:publish', ['--tag' => 'ai-routes', '--no-interaction' => true]);
        $this->make('server', $serverName);
        $this->make('tool', 'QuickstartTool');
        $this->make('resource', 'QuickstartResource');
        $this->make('prompt', 'QuickstartPrompt');

        $fc = file_get_contents(base_path('routes/ai.php')) ?: '';
        if (str_contains($fc, 'mcp.quickstart') === false) {
            file_put_contents(base_path('routes/ai.php'), "\nMcp::web('/mcp', App\\Mcp\\Servers\\{$serverName}::class)->name('mcp.quickstart');\n", FILE_APPEND);
        }

        $url = url('/mcp');
        $isSecure = str_contains($url, 'https://');

        $this->table(['Todo', 'Notes'], [
            ['Read', 'routes/ai.php'],
            ['Test with MCP Inspector', 'php artisan mcp:inspector mcp'],
            ['Test in your IDE', 'https://addmcp.fyi/Quickstart/'.url('/mcp')],
        ], 'box');
        $this->info('Routes file, server, tool, resource, and prompt all setup. Time to test.');

        if ($isSecure) {
            $this->line("Please note many AI agents use node and won't work with self-signed certificates locally. You may need to use http:// explicitly if you encounter issues.");
        }
    }

    protected function make(string $type, string $name): void
    {
        $this->call('make:mcp-'.$type, ['name' => $name, '--quickstart' => true, '--no-interaction' => true]);
    }
}
