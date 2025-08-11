# Laravel MCP Server SDK

> [!IMPORTANT]
> This package is still in development and not recommended for public usage. This package is currently only intended to power [Boost](https://github.com/laravel/boost).

---

## Introduction

Laravel MCP makes it easy to add MCP servers to your project and let AI talk to your apps.

## Installation

To get started, install Laravel MCP via the Composer package manager:

```bash
composer require laravel/mcp
```

Next, publish the `routes/ai.php` file to define your MCP servers:

```bash
php artisan vendor:publish --tag=ai-routes
```

The package will automatically register MCP servers defined in this file.

## Quickstart

**Create the Server and Tool**

First, create a new MCP server using the `mcp:server` Artisan command:

```bash
php artisan make:mcp-server DemoServer
```

Next, create a tool for the MCP server:

```bash
php artisan make:mcp-tool HelloTool
```

This will create two files: `app/Mcp/Servers/DemoServer.php` and `app/Mcp/Tools/HelloTool.php`.

**Add the Tool to the Server**

Open `app/Mcp/Servers/DemoServer.php` and add your new tool to the `$tools` property:

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

Finally, you can test it with the MCP Inspector tool:

```bash
php artisan mcp:inspector demo
```

## Creating Servers

A server is the central point that handles communication and exposes MCP methods, like tools and resources. Create a server with the `make:mcp-server` Artisan command:

```bash
php artisan make:mcp-server ExampleServer
```

## Creating Tools

[Tools](https://modelcontextprotocol.io/docs/concepts/tools) let your server expose functionality that clients can call, and that language models can use to perform actions, run code, or interact with external systems.

Use the `mcp:tool` Artisan command to generate a tool class:

```bash
php artisan make:mcp-tool ExampleTool
```

### Tool Inputs

Your tools can request arguments from the MCP client using a tool input schema:

```php
public function schema(ToolInputSchema $schema): ToolInputSchema
{
    $schema->string('iso_country_code')
        ->description('2 character country code')
        ->required();

    return $schema;
}
```

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

## Creating Resources

[Resources](https://modelcontextprotocol.io/docs/concepts/resources) let your server expose data and content that clients can read and use as context when interacting with language models.

Use the `make:mcp-resource` Artisan command to generate a resource class:

```bash
php artisan make:mcp-resource ExampleResource
```

To make a resource available to clients, you must register it in your server class in the `$resources` property.

## Creating Prompts

[Prompts](https://modelcontextprotocol.io/docs/concepts/prompts) let your server share reusable prompts that clients can use to prompt the LLM.

Use the `make:mcp-prompt` Artisan command to generate a prompt class:

```bash
php artisan make:mcp-prompt ExamplePrompt
```

To make a prompt available to clients, you must register it in your server class in the `$prompts` property.

## Registering Servers

The easiest way to register MCP servers is by publishing the `routes/ai.php` file included with the package. If this file exists, the package will automatically load any servers registered via the `Mcp` facade. You can expose a server over HTTP or make it available locally as an Artisan command.

### Web Servers

To register a web-based MCP server that can be accessed via HTTP POST requests, you should use the `web` method:

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

Run mcp:inspector to test your server:

```bash
php artisan mcp:inspector demo
```

This will run the MCP inspector and provide settings you can input to ensure it's setup correctly.

## Contributing

Thank you for considering contributing to Laravel MCP! You can read the contribution guide [here](.github/CONTRIBUTING.md).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

Please review [our security policy](https://github.com/laravel/mcp/security/policy) on how to report security vulnerabilities.

## License

Laravel MCP is open-sourced software licensed under the [MIT license](LICENSE.md).
