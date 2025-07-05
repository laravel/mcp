# 🤖 Laravel MCP

## Introduction
Laravel MCP gives you everything you need to build Laravel-powered MCP servers and let AI talk to your apps.

## Installation

To get started, install Laravel MCP via the Composer package manager:

```bash
composer require laravel/mcp
```

Next, you can optionally publish the `routes/ai.php` file to define your MCP servers:

```bash
php artisan vendor:publish --tag=ai-routes
```

The package will automatically register MCP server defined in this file.

## Quickstart

First, create a new MCP server using the `mcp:server` Artisan command:

```bash
php artisan mcp:server DemoServer
```

Next, create a tool for the MCP server:

```bash
php artisan mcp:tool HelloTool
```

This will create two files: `app/Mcp/Servers/DemoServer.php` and `app/Mcp/Tools/HelloTool.php`.

Open `app/Mcp/Tools/HelloTool.php` and replace its contents with the following code to create a simple tool that greets the user:

```php
<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Tools\Tool;
use Laravel\Mcp\Tools\ToolInputSchema;
use Laravel\Mcp\Tools\ToolResult;

class HelloTool extends Tool
{
    public function name(): string
    {
        return 'hello';
    }

    public function description(): string
    {
        return 'A friendly tool that says hello.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema
            ->string('name')
            ->description('The name to greet.')
            ->required();

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        $name = $arguments['name'];

        return ToolResult::text("Hello, {$name}!");
    }
}
```

Now, open `app/Mcp/Servers/DemoServer.php` and add your new tool to the `$tools` property:

```php
<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\HelloTool;
use Laravel\Mcp\Server;

class DemoServer extends Server
{
    public array $tools = [
        HelloTool::class,
    ];
}
```

Next, register your server in `routes/ai.php`:

```php
use App\Mcp\Servers\DemoServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('demo', DemoServer::class);
```

Finally, you can run your server and explore it with the MCP Inspector tool:

```bash
php artisan mcp:inspector demo
```

## Creating Servers

A server is the central point that handles communication and exposes MCP methods, like tools and resources. To create a server, extend the `Laravel\Mcp\Server` base class or use the `mcp:server` Artisan command to generate a server class:

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
    public string $serverName = 'Example MCP Server';

    public string $serverVersion = '1.0.0';

    public string $instructions = 'This server provides tools for X, Y, and Z.';

    public array $tools = [
        ExampleTool::class,
    ];
}
```

The tool's name is automatically determined from its class name. For example, `ExampleTool` will be exposed to clients as `example-tool`. The tool name can also be overwritten by adding a `name()` method to the tool.

The `Server` class has a few other properties you can override to customize its behavior:

-   `$defaultPaginationLength`: Controls the default number of tools returned to the client when listing available tools (defaults to 15).
-   `$maxPaginationLength`: Sets the maximum number of tools a client can request at a time when listing tools (defaults to 50).

## Creating Tools

Tools are a core building block of MCP. They let your server expose functionality that clients can call, and that language models can use to perform actions, run code, or interact with external systems. Each tool must extend the `Laravel\Mcp\Tools\Tool` abstract class. You can also use the `mcp:tool` Artisan command to generate a tool class:

```bash
php artisan mcp:tool ExampleTool
```

This will create a new tool class in `app/Mcp/Tools/ExampleTool.php`. Here's what a basic tool class looks like:

```php
<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Tools\Tool;
use Laravel\Mcp\Tools\ToolInputSchema;
use Laravel\Mcp\Tools\ToolResult;

class ExampleTool extends Tool
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
        $param1 = $arguments['param1'];
        $param2 = $arguments['param2'] ?? 0;

        // Perform some action
        $result = "Processed {$param1} and {$param2}.";

        return ToolResult::text($result);
    }
}
```

MCP clients use the schema to construct the tool call before calling the tool. The result is what is returned to the MCP client after the tool call has been executed.

### Annotating Tools

You can add annotations to your tools to provide hints to the MCP client about their behavior. This is done using PHP attributes on your tool class. Adding annotations to your tools is optional.

| Annotation        | Type    | Description                                                                                                           |
| ----------------- | ------- | --------------------------------------------------------------------------------------------------------------------- |
| `#[Title]`        | string  | A human-readable title for the tool.                                                                                  |
| `#[IsReadOnly]`   | boolean | Indicates the tool does not modify its environment.                                                                   |
| `#[IsDestructive]`| boolean | Indicates the tool may perform destructive updates. This is only meaningful when the tool is not read-only.             |
| `#[IsIdempotent]` | boolean | Indicates that calling the tool repeatedly with the same arguments has no additional effect. This is only meaningful when the tool is not read-only. |
| `#[IsOpenWorld]`  | boolean | Indicates the tool may interact with an "open world" of external entities.                                            |

Here's an example of how to add annotations to a tool:

```php
<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Tools\Annotations\Title;
use Laravel\Mcp\Tools\Tool;

#[Title('A read-only tool')]
#[IsReadOnly]
class ExampleTool extends Tool
{
    // ...
}
```

### Tool Results

The `handle` method of a tool must return an instance of `Laravel\Mcp\Tools\ToolResult`. This class provides a few convenient methods for creating responses.

#### Plain Text Result

For a simple text response, you can use the `text()` method:

```php
$response = ToolResult::text('This is a test response.');
```

#### Error Result

To indicate that the tool execution resulted in an error, use the `error()` method:

```php
$response = ToolResult::error('This is an error response.');
```

#### Result with Multiple Content Items

A tool result can contain multiple content items. The `items()` method allows you to construct a result from different content objects, like `TextContent`.

```php
$plainText = 'This is the plain text version.';
$markdown = 'This is the **markdown** version.';

$response = ToolResult::items(
    new TextContent($plainText),
    new TextContent($markdown)
);
```

## Creating Resources

Resources are one of the core building blocks of MCP. They let your server expose data and content that clients can read and use as context when interacting with language models. A resource must extend the `Laravel\Mcp\Resources\Resource` abstract class. You can use the `mcp:resource` Artisan command to generate a resource class:

```bash
php artisan mcp:resource ExampleResource
```

This will create a new resource class in `app/Mcp/Resources/ExampleResource.php`. Here's what a basic resource class looks like:

```php
<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Resources\Resource;

class ExampleResource extends Resource
{
    protected string $description = 'A description of what this resource contains.';

    /**
     * Return the resource contents.
     */
    public function read(): string
    {
        return 'Implement resource retrieval logic here';
    }
}
```

To make a resource available to clients, you must register it on your server. You can do this by adding the resource's class name to the `$resources` property of your server class:

```php
<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\ExampleResource;
use Laravel\Mcp\Server;

class ExampleServer extends Server
{
    // ...

    public array $resources = [
        ExampleResource::class,
    ];
}
```

### Resource Results

A resource class defines a `read()` method that returns its content. By default, you can return a simple string, and the package will try to determine if it's plain text or binary data. Binary data is automatically `base64` encoded before being returned to the client.

```php
// Return plain text
public function read(): string
{
    return 'This is the content of the resource.';
}

// Return binary data
public function read(): string
{
    return file_get_contents('/path/to/image.png');
}
```

For more explicit control, you can return a `Laravel\Mcp\Resources\Results\Text` or `Laravel\Mcp\Resources\Results\Blob` object. This ensures the content type is handled exactly as you intend.

```php
use Laravel\Mcp\Contracts\Resources\Content;
use Laravel\Mcp\Resources\Results\Blob;

public function read(): Content
{
    return new Blob(file_get_contents('/path/to/image.png'));
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

Web servers can be protected using Laravel Passport, turning your MCP server into an OAuth2 protected resource.

First, add the `Mcp::oauthRoutes()` helper to your `routes/web.php` file. This registers the required OAuth2 discovery and client registration endpoints. The method accepts an optional prefix, which defaults to `oauth`.

```php
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();
```

Then, apply the `auth:api` middleware to your server registration in `routes/ai.php`:

```php
use App\Mcp\Servers\ExampleServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('demo', ExampleServer::class)
    ->middleware('auth:api');
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

## What's Not Included (Yet!)

Some features from the MCP specification that are not yet implemented:

-   **`listChanged` notifications for tools:** The server doesn't proactively notify clients when the list of available tools changes. This would require a long-lived SSE connection for HTTP-based connections.
-   **Image and audio content for tool results:** The `ToolResult` class currently supports text content, but not yet image or audio content types from the specification.
-   **Capability Negotiation:** The server doesn't yet have logic for negotiating these capabilities with the client during initialization. There's currently no way to know the client's capabilities in subsequent requests (would require a long-lived session).
-   **Timeouts:** The package doesn't have built-in handling for timeouts, which should result in a specific JSON-RPC error.
-   **Long-lived Sessions:** The server currently operates in a stateless manner and doesn't support long-lived SSE sessions for things where the server initiates requests with the client. This is only possible for local server running on STDIO currently.
