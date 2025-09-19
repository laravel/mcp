<?php

declare(strict_types=1);

namespace Laravel\Mcp\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
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
        $route = $registrar->getWebServer($handle);

        $servers = $registrar->servers();
        if ($servers === []) {
            $this->components->error('No MCP servers found. Please run `php artisan make:mcp-server [name]`');

            return static::FAILURE;
        }

        // Only one server, we should just run it for them
        if (count($servers) === 1) {
            $server = array_shift($servers);
            [$localServer, $route] = match (true) {
                is_callable($server) => [$server, null],
                $server::class === Route::class => [null, $server],
                default => [null, null],
            };
        }

        if (is_null($localServer) && is_null($route)) {
            $availableServers = Arr::map(array_keys($servers), fn ($server): string => "[{$server}]");
            $this->components->error('MCP Server with name ['.$handle.'] not found. Available servers: '.Arr::join($availableServers, ', '));

            return static::FAILURE;
        }

        $env = [];

        if ($localServer !== null) {
            $artisanPath = base_path('artisan');

            $command = [
                'npx',
                '@modelcontextprotocol/inspector',
                '--transport',
                'stdio',
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
            $serverUrl = url($route->uri());
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

            $process->mustRun(function (int|string $type, string $buffer): void {
                echo $buffer;
            });
        } catch (Exception $exception) {
            $this->components->error('Failed to start MCP Inspector: '.$exception->getMessage());

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
