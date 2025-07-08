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

The package will automatically register MCP servers defined in this file.

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

use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

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
use Laravel\Mcp\Server\Facades\Mcp;

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

Tools let your server expose functionality that clients can call, and that language models can use to perform actions, run code, or interact with external systems. Each tool must extend the `Laravel\Mcp\Server\Tool` abstract class. You can also use the `mcp:tool` Artisan command to generate a tool class:

```bash
php artisan mcp:tool ExampleTool
```

This will create a new tool class in `app/Mcp/Tools/ExampleTool.php`. Here's what a basic tool class looks like:

```php
<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

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

| Annotation         | Type    | Description                                                                                                                                          |
| ------------------ | ------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| `#[Title]`         | string  | A human-readable title for the tool.                                                                                                                 |
| `#[IsReadOnly]`    | boolean | Indicates the tool does not modify its environment.                                                                                                  |
| `#[IsDestructive]` | boolean | Indicates the tool may perform destructive updates. This is only meaningful when the tool is not read-only.                                          |
| `#[IsIdempotent]`  | boolean | Indicates that calling the tool repeatedly with the same arguments has no additional effect. This is only meaningful when the tool is not read-only. |
| `#[IsOpenWorld]`   | boolean | Indicates the tool may interact with an "open world" of external entities.                                                                           |

Here's an example of how to add annotations to a tool:

```php
<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tool;

#[Title('A read-only tool')]
#[IsReadOnly]
class ExampleTool extends Tool
{
    // ...
}
```

### Tool Results

The `handle` method of a tool must return an instance of `Laravel\Mcp\Server\Tools\ToolResult`. This class provides a few convenient methods for creating responses.

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
use Laravel\Mcp\Server\Tools\TextContent;

$plainText = 'This is the plain text version.';
$markdown = 'This is the **markdown** version.';

$response = ToolResult::items(
    new TextContent($plainText),
    new TextContent($markdown)
);
```

## Creating Resources

Resources let your server expose data and content that clients can read and use as context when interacting with language models. A resource must extend the `Laravel\Mcp\Server\Resource` abstract class. You can use the `mcp:resource` Artisan command to generate a resource class:

```bash
php artisan mcp:resource ExampleResource
```

This will create a new resource class in `app/Mcp/Resources/ExampleResource.php`. Here's what a basic resource class looks like:

```php
<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Server\Resource;

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

For more explicit control, you can return a `Laravel\Mcp\Server\Resources\Results\Text` or `Laravel\Mcp\Server\Resources\Results\Blob` object. This ensures the content type is handled exactly as you intend.

```php
use Laravel\Mcp\Server\Contracts\Resources\Content;
use Laravel\Mcp\Server\Resources\Results\Blob;

public function read(): Content
{
    return new Blob(file_get_contents('/path/to/image.png'));
}
```

## Registering Servers

The easiest way to register MCP servers is by publishing the `routes/ai.php` file included with the package. If this file exists, the package will automatically load any servers registered via the `Mcp` facade. You can expose a server over HTTP or make it available locally as an Artisan command.

### Web Servers

To register a web-based MCP server that can be accessed via HTTP POST requests:

```php
use App\Mcp\Servers\ExampleServer;
use Laravel\Mcp\Server\Facades\Mcp;

Mcp::web('demo', ExampleServer::class);
```

This will make `ExampleServer` available at the `/mcp/demo` endpoint.

> **Security Note:** Exposing a local development server via `Mcp::web()` can make your application vulnerable to DNS rebinding attacks. If you must expose a local MCP server over HTTP, it is critical to validate the `Host` and `Origin` headers on incoming requests to ensure they are coming from a trusted source.

### Local Servers

To register a local MCP server that can be run as an Artisan command:

```php
use App\Mcp\Servers\ExampleServer;
use Laravel\Mcp\Server\Facades\Mcp;

Mcp::local('demo', ExampleServer::class);
```

This makes the server available via the `mcp:start` Artisan command:

```bash
php artisan mcp:start demo
```

## Authentication

Web-based MCP servers can be protected using [Laravel Passport](laravel.com/docs/passport), turning your MCP server into an OAuth2 protected resource.

If you already have Passport set up for your app, all you need to do is add the `Mcp::oauthRoutes()` helper to your `routes/web.php` file. This registers the required OAuth2 discovery and client registration endpoints. The method accepts an optional route prefix, which defaults to `oauth`.

```php
use Laravel\Mcp\Server\Facades\Mcp;

Mcp::oauthRoutes();
```

Then, apply the `auth:api` middleware to your server registration in `routes/ai.php`:

```php
use App\Mcp\Servers\ExampleServer;
use Laravel\Mcp\Server\Facades\Mcp;

Mcp::web('demo', ExampleServer::class)
    ->middleware('auth:api');
```

Your MCP server is now protected using OAuth.

## Testing Servers With the MCP Inspector Tool

The [MCP Inspector](https://modelcontextprotocol.io/docs/tools/inspector) is an interactive tool for testing and debugging your MCP servers. You can use it to connect to your server, verify authentication, and try out tools, resources, and other parts of the protocol.

For local servers, you can start the inspector pre-configured for your server by using the `mcp:inspector` Artisan command:

```bash
php artisan mcp:inspector dev-assistant
```

For web-based servers, the inspector must be started and configured manually:

```bash
npx @modelcontextprotocol/inspector
```

## Streaming Tool Responses

For tools that send multiple updates or stream large amounts of data, you can return a generator from the `handle()` method. For web-based servers, this automatically opens an SSE stream and sends an event for each message the generator yields.

Within your generator, you can yield any number of `Laravel\Mcp\Server\Tools\ToolNotification` instances to send intermediate updates to the client. When you're done, yield a single `Laravel\Mcp\Server\Tools\ToolResult` to complete the execution.

This is particularly useful for long-running tasks or when you want to provide real-time feedback to the client, such as streaming tokens in a chat application:

```php
<?php

namespace App\Mcp\Tools;

use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolNotification;
use Laravel\Mcp\Server\Tools\ToolResult;

class ChatStreamingTool extends Tool
{
    public function handle(array $arguments): Generator
    {
        $tokens = explode(' ', $arguments['message']);

        foreach ($tokens as $token) {
            yield new ToolNotification('chat/token', ['token' => $token . ' ']);
        }

        yield ToolResult::text("Message streamed successfully.");
    }
}
```

## Programmatically Adding Tools

In addition to registering tools via the `$tools` property on your server, you can also add them programmatically by overriding the `boot()` method.

The `addTool()` method accepts an instance of a class that extends `Laravel\Mcp\Server\Tool`. You can pass a class name or define one on-the-fly with an anonymous class:

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

## Extending Server Capabilities

If you want to add you own JSON-RPC methods to the server to support other MCP features, you can override the `boot()` method to register them.

Here's a simple example of how to support the [Ping](https://modelcontextprotocol.io/specification/2025-03-26/basic/utilities/ping) method, the simplest method in the MCP protocol:

First, define your method handler. This class must implement the `Laravel\Mcp\Server\Contracts\Methods\Method` interface:

```php
<?php

namespace App\Mcp\Methods;

use Laravel\Mcp\Server\Contracts\Methods\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;
use Laravel\Mcp\Server\Transport\JsonRpcMessage;

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

### Registering Capabilities

Once you’ve added a custom method to your server, you may want to let the client know about it during the initialize handshake. You can do this by adding custom capabilities in the boot() method:

```php
public function boot(): void
{
    $this->addCapability('customFeature.enabled', true);
}
```

## Contributing

Thank you for considering contributing to Laravel MCP! You can read the contribution guide [here](.github/CONTRIBUTING.md).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

Please review [our security policy](https://github.com/laravel/mcp/security/policy) on how to report security vulnerabilities.

## License

Laravel MCP is open-sourced software licensed under the [MIT license](LICENSE.md).
