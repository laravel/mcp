<?php

declare(strict_types=1);

namespace Laravel\Mcp\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Laravel\Mcp\Server\Registrar;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'mcp:inspector',
    description: 'Open the MCP inspector tool to debug and test MCP servers'
)]
class McpInspectorCommand extends Command
{
    public function handle(): int
    {
        $handle = $this->argument('handle');
        if (! is_string($handle)) {
            $this->error('Please pass a valid MCP server handle');

            return static::FAILURE;
        }

        /** @var Registrar $registrar */
        $registrar = Container::getInstance()->make('mcp');

        $this->info("Starting the MCP Inspector for server: {$handle}");

        $localServer = $registrar->getLocalServer($handle);
        $webServer = $registrar->getWebServer($handle);

        if (is_null($localServer) && is_null($webServer)) {
            $this->error('Please pass a valid MCP handle or route');

            return static::FAILURE;
        }

        $env = [];

        if ($localServer) {
            $currentDir = getcwd();
            $command = [
                'npx',
                '@modelcontextprotocol/inspector',
                $this->phpBinary(),
                $currentDir.'/artisan',
                "mcp:start {$handle}",
            ];

            $guidance = [
                'Transport Type' => 'STDIO',
                'Command' => $this->phpBinary(),
                'Arguments' => implode(' ', [base_path('/artisan'), 'mcp:start', $handle]),
            ];
        } else {
            $serverUrl = route($registrar->routeName($handle));
            if (parse_url($serverUrl, PHP_URL_SCHEME) === 'https') {
                $env['NODE_TLS_REJECT_UNAUTHORIZED'] = '0';
            }

            $command = [
                'npx',
                '@modelcontextprotocol/inspector',
                '--transport',
                'http',
                '--server-url',
                $serverUrl,
            ];

            $guidance = [
                'Transport Type' => 'Streamable HTTP',
                'URL' => $serverUrl,
                'Secure' => 'Your project must be accessible on HTTP for this to work due to how node manages SSL trust',
            ];
        }

        $process = new Process($command, null, $env);
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
            ['handle', InputArgument::REQUIRED, 'The handle or route of the MCP server to inspect.'],
        ];
    }

    protected function phpBinary(): string
    {
        return (new PhpExecutableFinder)->find(false) ?: 'php';
    }
}
