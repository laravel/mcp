<?php

declare(strict_types=1);

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
        if (! is_string($handle)) {
            $this->error('Please pass a valid MCP server handle');

            return self::FAILURE;
        }

        /** @var \Laravel\Mcp\Server\Registrar $registrar */
        $registrar = app('mcp');

        $this->info("Starting the MCP Inspector for server: {$handle}");

        // Check if this is a local server
        $localServer = $registrar->getLocalServer($handle);
        $webServer = $registrar->getWebServer($handle);

        if (is_null($localServer) && is_null($webServer)) {
            $this->error('Please pass a valid MCP handle');

            return self::FAILURE;
        }

        if ($localServer) {
            // Local server - use STDIO transport
            $currentDir = getcwd();
            $command = [
                'npx',
                '@modelcontextprotocol/inspector',
                php_binary(),
                $currentDir.'/artisan',
                "mcp:start {$handle}",
            ];

            $guidance = [
                'Transport Type' => 'STDIO',
                'Command' => php_binary(),
                'Arguments' => implode(' ', [base_path('/artisan'), 'mcp:start', $handle]),
            ];
        } else {
            // It's a web MCP
            $serverUrl = str_replace('https://', 'http://', route('mcp-server.'.$handle));

            $command = [
                'npx',
                '@modelcontextprotocol/inspector',
                $serverUrl,
            ];

            $guidance = [
                'Transport Type' => 'Streamable HTTP',
                'URL' => $serverUrl,
                'Secure' => 'Your project must be accessible on HTTP for this to work due to how node manages SSL trust',
            ];
        }

        $process = new Process($command);
        $process->setTimeout(null);

        try {
            foreach ($guidance as $guidanceKey => $guidanceValue) {
                $this->info(sprintf('%s => %s', $guidanceKey, $guidanceValue));
            }
            $this->newLine();
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
