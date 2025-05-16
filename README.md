# Laravel MCP (Model Context Protocol)

[Model Context Protocol (MCP)](https://modelcontextprotocol.io/) is an open protocol that standardizes how applications provide context to Large Language Models (LLMs). It aims to be like a USB-C port for AI applications, enabling standardized connections between AI models and various data sources or tools.

This Laravel package helps you build MCP-compliant servers within your Laravel applications. These servers can then expose tools that AI agents can use. It provides a structured way to define servers and their capabilities (tools), accessible via streamed HTTP (web) or STDIO (local artisan commands).

## Table of Contents

- [Setup](#setup)
  - [Publishing Routes](#publishing-routes)
- [Caveats](#caveats)
- [Creating a Server](#creating-a-server)
- [Creating Tools](#creating-tools)
- [Registering Servers](#registering-servers)
  - [Web Servers](#web-servers)
  - [Local (CLI) Servers](#local-cli-servers)
- [Testing Servers with MCP Inspector](#testing-servers-with-mcp-inspector)

## Setup

### Publishing Routes

The package offers an optional route file `routes/ai.php` to define your MCP servers. To publish this file to your application\'s `routes` directory, run the following Artisan command:

```bash
php artisan vendor:publish --tag=ai-routes
```

The package automatically loads routes defined in this file. Web routes will be prefixed with `/mcp`.

## Caveats

- **No tests yet.** This is an early-stage package, and I haven't written any tests for it yet.
- **SSE transport is missing.** I haven't implemented an SSE transport layer. Cursor, for example, uses this for HTTP (even though it's actually deprecated in the protocol), so you'll need to use STDIO for local connections or the basic HTTP streaming for web if your client handles that.
- **It's a prototype.** This is very much a prototype. My main goal right now is to nail the developer experience (DX), so things will change.

## Creating a Server

A server is the central point that handles communication and exposes tools. To create a server, you need to extend the `Laravel\Mcp\Server` base class.

```php
<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use App\Mcp\Tools\MyExampleTool;

class ExampleServer extends Server
{
    /**
     * The display name of your server.
     *
     * @var string
     */
    protected $serverName = 'My Custom MCP Server';

    /**
     * The version of your server.
     *
     * @var string
     */
    protected $serverVersion = '1.0.0';

    /**
     * Instructions or a welcome message for clients connecting to the server.
     *
     * @var string
     */
    protected $instructions = 'Welcome! This server provides tools for X, Y, and Z.';

    /**
     * An array of tool classes that this server provides.
     *
     * @var array
     */
    protected $tools = [
        'example_tool' => MyExampleTool::class,
    ];
}

```

## Creating Tools

Tools are individual units of functionality that your server exposes. Each tool must implement the `Laravel\Mcp\Contracts\Tool` interface.

```php
<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Contracts\Tool;

class MyExampleTool implements Tool
{
    /**
     * Get the unique name of the tool.
     * This name is used by clients to call the tool.
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'my_example_tool'; // Should match the key in Server\'s $tools array
    }

    /**
     * Get a description of what the tool does.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'This is an example tool that performs a sample action.';
    }

    /**
     * Get the JSON schema for the tool\'s input arguments.
     *
     * @return array
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'param1' => [
                    'type' => 'string',
                    'description' => 'The first parameter for this tool.',
                ],
                'param2' => [
                    'type' => 'integer',
                    'description' => 'The second parameter, an integer.',
                ],
            ],
            'required' => ['param1'],
        ];
    }

    /**
     * Execute the tool\'s logic with the provided arguments.
     *
     * @param array $arguments The arguments for the tool, matching the input schema.
     * @return array The result of the tool\'s execution.
     */
    public function call(array $arguments): array
    {
        // Your tool logic here
        $param1 = $arguments['param1'] ?? 'default';
        $param2 = $arguments['param2'] ?? 0;

        // Perform some action
        $result = "Processed {$param1} and {$param2}.";

        return [
            'content' => [[
                'type' = 'text',
                'text' => $result,
            ]]
        ];
    }
}

```

## Registering Servers

Servers are registered in the `routes/ai.php` file using the `Mcp` facade (or in a different routes file). You can register a server to be accessible via the web (HTTP) or locally (as an Artisan command).

### Web Servers

To register a server that can be accessed via an HTTP POST request:

```php
use App\Mcp\Servers\ExampleServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('demo', ExampleServer::class);
```
This will make `ExampleServer` available at the `/mcp/demo` endpoint.

### Local (CLI) Servers

To register a server that can be run as an Artisan command:

```php
use App\Mcp\Servers\ExampleServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('demo', ExampleServer::class);
```
This will create an Artisan command `php artisan mcp:demo` to connect to the server.

## Testing Servers with MCP Inspector

The [MCP Inspector](https://modelcontextprotocol.io/docs/tools/inspector) is an interactive tool for testing and debugging MCP servers. It allows you to connect to your server, inspect tools, and test them with custom inputs.
