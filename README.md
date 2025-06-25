# Laravel MCP (Model Context Protocol)

[Model Context Protocol (MCP)](https://modelcontextprotocol.io/) is an open protocol that standardizes how applications provide context to Large Language Models (LLMs). It aims to be like a USB-C port for AI applications, enabling standardized connections between AI models and various data sources or tools.

This Laravel package helps you build MCP-compliant servers within your Laravel applications. These servers can then expose tools that AI agents can use. It provides a structured way to define servers and their capabilities (tools), accessible via streamed HTTP (web) or STDIO (local artisan commands).

## Table of Contents

- [Setup](#setup)
  - [Publishing Routes](#publishing-routes)
- [Creating a Server](#creating-a-server)
- [Creating Tools](#creating-tools)
  - [Tool Results](#tool-results)
- [Registering Servers](#registering-servers)
  - [Web Servers](#web-servers)
  - [Local Servers](#local-servers)
- [Authentication](#authentication)
- [Testing Servers with MCP Inspector](#testing-servers-with-mcp-inspector)
- [Advanced](#advanced)
  - [Streaming Responses](#streaming-responses)
  - [Dynamically Adding Tools](#dynamically-adding-tools)
  - [Dynamically Adding Methods](#dynamically-adding-methods)

## Setup

### Publishing Routes

The package offers an optional route file `routes/ai.php` to define your MCP servers. To publish this file to your application\'s `routes` directory, run the following Artisan command:

```bash
php artisan vendor:publish --tag=ai-routes
```

The package automatically loads routes defined in this file. Web routes will be prefixed with `/mcp`.

## Creating a Server

A server is the central point that handles communication and exposes tools. To create a server, you can extend the `Laravel\Mcp\Server` base class or use the `mcp:server` Artisan command to generate a server class:

```bash
php artisan mcp:server ExampleServer
```

This will create a new server class in `app/Mcp/Servers/ExampleServer.php`. Here's what a basic server class looks like:

```php
<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use App\Mcp\Tools\MyExampleTool;

class ExampleServer extends Server
{
    public string $serverName = 'My Custom MCP Server';

    public string $serverVersion = '1.0.0';

    public string $instructions = 'Welcome! This server provides tools for X, Y, and Z.';

    public array $tools = [
        MyExampleTool::class,
    ];
}
```

The tool's name is automatically determined from its class name. For example, `MyExampleTool` will be exposed to clients as `my-example-tool`. The tool name can also be overwritten by adding a `name()` method to the tool.

The `Server` class has a few other properties you can override to customize its behavior:

-   `$defaultPaginationLength`: Controls the default number of tools returned by the `tools/list` method if the client doesn't specify a limit (defaults to `15`).
-   `$maxPaginationLength`: Sets the maximum number of tools a client can request via `tools/list` in a single call (defaults to `50`).

## Creating Tools

Tools are individual units of functionality that your server exposes. Each tool must extend the `Laravel\Mcp\Tools\Tool` abstract class. You can also use the `mcp:tool` Artisan command to generate a tool class:

```bash
php artisan mcp:tool MyExampleTool
```

This will create a new tool class in `app/Mcp/Tools/MyExampleTool.php`. Here's what a basic tool class looks like:

```php
<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Tools\Tool;
use Laravel\Mcp\Tools\ToolInputSchema;
use Laravel\Mcp\Tools\ToolResult;

class MyExampleTool extends Tool
{
    public function description(): string
    {
        return 'This is an example tool that performs a sample action.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('param1')
            ->description('The first parameter for this tool.')
            ->required();

        $schema->integer('param2')
            ->description('The second parameter, an integer.');

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        // Your tool logic here
        $param1 = $arguments['param1'] ?? 'default';
        $param2 = $arguments['param2'] ?? 0;

        // Perform some action
        $result = "Processed {$param1} and {$param2}.";

        return ToolResult::text($result);
    }
}
```

### Tool Results

The `handle` method of a tool must return an instance of `Laravel\Mcp\Tools\ToolResult`. This class provides a few convenient methods for creating responses.

#### Plain Text Response

For a simple text response, you can use the `text()` method:

```php
$response = ToolResult::text('This is a test response.');
```

#### Error Response

To indicate that the tool execution resulted in an error, use the `error()` method:

```php
$response = ToolResult::error('This is a test error response.');
```

#### Multiple Content Items

A tool result can contain multiple content items. The `items()` method allows you to construct a result from different content objects, like `TextContent`.

```php
$plainText = 'This is the plain text version.';
$markdown = 'This is the **markdown** version.';

$response = ToolResult::items(
    new TextContent($plainText),
    new TextContent($markdown)
);
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

> **Security Note:** Exposing a local development server via `Mcp::web()` can make your application vulnerable to DNS rebinding attacks. If you must expose a local server, it is critical to validate the `Host` and `Origin` headers on incoming requests to ensure they are coming from a trusted source.

### Local Servers

To register a server that can be run as an Artisan command:

```php
use App\Mcp\Servers\ExampleServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('demo', ExampleServer::class);
```
This makes the server available via the `mcp:start` Artisan command:

```bash
php artisan mcp:start demo
```

## Authentication

For web servers, you can easily add authentication using Laravel Sanctum. Just append the `auth:sanctum` middleware to your server registration in the `routes/ai.php` file:

```php
use App\Mcp\Servers\ExampleServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('demo', ExampleServer::class)
    ->middleware('auth:sanctum');
```

## Testing Servers with MCP Inspector

The [MCP Inspector](https://modelcontextprotocol.io/docs/tools/inspector) is an interactive tool for testing and debugging MCP servers. It allows you to connect to your server, inspect tools, and test them with custom inputs.

For local servers, you can start the inspector pre-configured for your server by using the `mcp:inspector` Artisan command. For example, if your server handle is `dev-assistant`, you would run:

```bash
php artisan mcp:inspector dev-assistant
```

For web-based servers, the inspector must be started and configured manually:

```bash
npx @modelcontextprotocol/inspector
```

## Advanced

### Streaming Responses

For tools that need to send multiple updates to the client before completing, or that produce a large amount of data, you can return a generator from the `handle` method. For web-based servers, this will automatically open an SSE stream to the client.

Within your generator, you can `yield` instances of `Laravel\Mcp\Tools\ToolNotification` for intermediate updates and finally `yield` a single `Laravel\Mcp\Tools\ToolResult` for the main result of the tool execution.

This is particularly useful for long-running tasks or when you want to provide real-time feedback to the client, such as streaming tokens in a chat application.

```php
<?php

namespace App\Mcp\Tools;

use Generator;
use Laravel\Mcp\Tools\Tool;
use Laravel\Mcp\Tools\ToolInputSchema;
use Laravel\Mcp\Tools\ToolNotification;
use Laravel\Mcp\Tools\ToolResult;

class ChatStreamingTool extends Tool
{
    public function description(): string
    {
        return 'A tool that streams a chat response.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->string('message')->description('The message to stream back.');
    }

    public function handle(array $arguments): Generator
    {
        $message = $arguments['message'] ?? "Here's a message from the chat bot.";
        $tokens = explode(' ', $message);

        foreach ($tokens as $token) {
            yield new ToolNotification('chat/token', ['token' => $token . ' ']);

            usleep(100000);
        }

        yield ToolResult::text("Message streamed successfully.");
    }
}
```

### Dynamically Adding Tools

In addition to registering tools via the `$tools` property on your server, you can also add them dynamically within the `boot()` method. This is useful when the availability of a tool depends on runtime conditions, such as application configuration.

The `addTool()` method accepts an instance of a class that extends `Laravel\Mcp\Tools\Tool`. You can pass a pre-existing tool class instance or define one on-the-fly with an anonymous class.

Here's how you can add a tool using an anonymous class inside your server's `boot()` method:

```php
public function boot(): void
{
    $this->addTool(new class extends Tool {
        public function description(): string
        {
            return 'A dynamically registered tool.';
        }

        public function schema(ToolInputSchema $schema): ToolInputSchema
        {
            return $schema;
        }

        public function handle(array $arguments): ToolResult
        {
            return ToolResult::text('Dynamic tool was called!');
        }
    });
}
```

### Dynamically Adding Methods

If you want to add you own JSON-RPC methods to the server to support other MCP features, you can use the `boot()` method to register them. This is helpful if you want your MCP server to support methods that still aren't supported by this package, such as [Resources](https://modelcontextprotocol.io/specification/2025-03-26/server/resources) and [Prompts](https://modelcontextprotocol.io/specification/2025-03-26/server/prompts).

Here's a simple example of how to support the [Ping](https://modelcontextprotocol.io/specification/2025-03-26/basic/utilities/ping) method, the simplest method in the MCP protocol:

First, define your method handler. This class must implement the `Laravel\Mcp\Contracts\Methods\Method` interface:

```php
<?php

namespace App\Mcp\Methods;

use Laravel\Mcp\Contracts\Methods\Method;
use Laravel\Mcp\ServerContext;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Mcp\Transport\JsonRpcMessage;

class PingMethod implements Method
{
    public function handle(JsonRpcMessage $request, ServerContext $context): JsonRpcResponse
    {
        // For a ping, we just return an empty result
        return new JsonRpcResponse(
            id: $request->id,
            result: []
        );
    }
}
```

Then, in your server class, override the `boot()` method and use `addMethod()` to register your custom method:

```php
<?php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use App\Mcp\Methods\PingMethod;

class ExampleServer extends Server
{
    public function boot(): void
    {
        $this->addMethod('ping', PingMethod::class);
    }
}
```

Now, your server will be able to handle `ping` requests.

### Dynamically Adding Capabilities

You can add custom capabilities to your server in the `boot()` method. This is useful for advertising support for non-standard features to the client during the `initialize` handshake. The client can then check for these capabilities and adjust its behavior accordingly.

```php
public function boot(): void
{
    $this->addCapability('customFeature.enabled', true);
}
```
