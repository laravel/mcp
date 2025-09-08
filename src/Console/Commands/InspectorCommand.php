<?php

declare(strict_types=1);

namespace Laravel\Mcp\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Laravel\Mcp\Server\Registrar;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'mcp:inspector',
    description: 'Open the MCP Inspector tool to debug and test MCP Servers.'
)]
class InspectorCommand extends Command
{
    public function handle(Registrar $registrar): int
    {
        $handle = $this->argument('handle');

        if (! is_string($handle)) {
            $this->components->error('Please pass a valid MCP server handle');

            return static::FAILURE;
        }

        $this->components->info("Starting the MCP Inspector for server [{$handle}]");

        $localServer = $registrar->getLocalServer($handle);
        $webServer = $registrar->getWebServer($handle);

        if (is_null($localServer) && is_null($webServer)) {
            $this->components->error("MCP Server with handle [{$handle}] not found.");

            return static::FAILURE;
        }

        if ($localServer) {
            $artisanPath = base_path('artisan');

            $command = [
                'npx',
                '@modelcontextprotocol/inspector',
                $this->phpBinary(),
                $artisanPath,
                "mcp:start {$handle}",
            ];

            $guidance = [
                'Transport Type' => 'STDIO',
                'Command' => $this->phpBinary(),
                'Arguments' => implode(' ', [
                    str_replace('\\', '/', $artisanPath),
                    'mcp:start',
                    $handle,
                ]),
            ];
        } else {
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

            $process->mustRun(function (int $type, string $buffer) {
                echo $buffer;
            });
        } catch (Exception $e) {
            $this->components->error('Failed to start MCP Inspector: '.$e->getMessage());

            return static::FAILURE;
        }

        return static::SUCCESS;
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getArguments(): array
    {
        return [
            ['handle', InputArgument::REQUIRED, 'The handle of the MCP server to inspect.'],
        ];
    }

    protected function phpBinary(): string
    {
        return (new PhpExecutableFinder)->find(false) ?: 'php';
    }
}
