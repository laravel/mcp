# Laravel MCP Server SDK

> [!IMPORTANT]
> This package is still in development and not recommended for public usage. This package is currently only intended to power [Boost](https://github.com/laravel/boost).

---

## Introduction

Laravel MCP makes it easy to add MCP servers to your project and let AI clients interact with your application. It provides an expressive, fluent interface for defining servers, tools, resources, and prompts.

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
php artisan make:mcp-server WeatherServer
```

Next, create a tool for the MCP server:

```bash
php artisan make:mcp-tool CurrentWeatherTool
```

This will create two files: `app/Mcp/Servers/WeatherServer.php` and `app/Mcp/Tools/CurrentWeatherTool.php`.

**Add the Tool to the Server**

Open `app/Mcp/Servers/WeatherServer.php` and add your new tool to the `$tools` property:

```php
<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CurrentWeatherTool;
use Laravel\Mcp\Server;

class WeatherServer extends Server
{
    //

    public array $tools = [
        CurrentWeatherTool::class,
    ];
}
```

Next, register your server in `routes/ai.php`:

```php
use App\Mcp\Servers\DemoServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('weather', WeatherServer::class);
```

Finally, you can test it with the MCP Inspector tool:

```bash
php artisan mcp:inspector weather
```

## Creating Servers

A server is the central point that handles communication and exposes MCP methods, like tools and resources. Create a server with the `make:mcp-server` Artisan command:

```bash
php artisan make:mcp-server WeatherExample
```

## Creating Tools

[Tools](https://modelcontextprotocol.io/docs/concepts/tools) let your server expose functionality that clients can call, and that language models can use to perform actions, run code, or interact with external systems.

Use the `mcp:tool` Artisan command to generate a tool class:

```bash
php artisan make:mcp-tool WeatherTool
```

To make a tool available to clients, you must register it in your server class in the `$tools` property.

### Tool Inputs

Your tools can request arguments from the MCP client using a tool input schema:

```php
use Illuminate\JsonSchema\JsonSchema;

public function schema(JsonSchema $schema): array
{
    return [
        'location' => $schema->string()
            ->description('The location to get the weather for')
            ->required(),
    ];
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

#[Title('Weather Tool')]
#[IsReadOnly]
class WeatherTool extends Tool
{
    // ...
}
```

### Validating Tool Arguments

You may validate tool's request arguments in the `handle` method using Laravel's built-in validation features.

```php
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Response;

public function handle(Request $request): Response
{
    $request->validate([
        'location' => 'required|string|max:50',
    ]);

    $location = $request->string('location');
    
    return Response::text("The weather in {$location} is sunny.");
}
```

### Tool Responses

The `handle` method of a tool must return an instance of `Laravel\Mcp\Response`. This class provides a few convenient methods for creating responses.

#### Plain Text

For a simple text response, you can use the `text()` method:

```php
$response = Response::text('This is a test response.');
```

#### Errors

To indicate that the tool execution resulted in an error, use the `error()` method:

```php
$response = Response::error('This is an error response.');
```

#### Multiple Responses

If your tool returns multiple pieces of content, you can return an array of `Laravel\Mcp\Response` instances. Each response can contain different types of content, such as plain text or markdown:

```php
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;

public function handle(Request $request): Response
{
    $plainText = 'This is the plain text version.';
    $markdown = 'This is the **markdown** version.';
    
    return [
        Response::text($plainText),
        Response::text($markdown),
    ];
}
```

## Streaming Responses

For tools that send multiple updates or stream large amounts of data, you can return a generator from the `handle()` method. For web-based servers, this automatically opens an SSE stream and sends an event for each message the generator yields.

Within your generator, you can yield any number of notifications to send intermediate updates to the client. When you're done, yield a single `Laravel\Mcp\Response` to complete the execution.

This is particularly useful for long-running tasks or when you want to provide real-time feedback to the client, such as streaming tokens in a chat application:

```php
<?php

namespace App\Mcp\Tools;

use Generator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ChatStreamingTool extends Tool
{
    public function handle(Request $request): Generator
    {
        $tokens = $request->string('message')->explode(' ');

        foreach ($tokens as $token) {
            yield Response::notificaton('chat/token', ['token' => $token . ' ']);
        }

        yield Response::text("Message streamed successfully.");
    }
}
```

## Creating Resources

[Resources](https://modelcontextprotocol.io/docs/concepts/resources) let your server expose data and content that clients can read and use as context when interacting with language models.

Use the `make:mcp-resource` Artisan command to generate a resource class:

```bash
php artisan make:mcp-resource WeatherGuidelinesResource
```

To make a resource available to clients, you must register it in your server class in the `$resources` property.

## Creating Prompts

[Prompts](https://modelcontextprotocol.io/docs/concepts/prompts) let your server share reusable prompts that clients can use to prompt the LLM.

Use the `make:mcp-prompt` Artisan command to generate a prompt class:

```bash
php artisan make:mcp-prompt AskWeatherPrompt
```

To make a prompt available to clients, you must register it in your server class in the `$prompts` property.

### Creating Prompt Arguments

You can define arguments for your prompt using the `arguments` method:

```php
use Laravel\Mcp\Server\Prompts\Argument;

public function arguments(): array
{
    return [
        new Argument(
            name: 'language',
            description: 'The language the code is in',
            required: true,
        ),
    ];
}
```

### Validating Prompt Arguments

You may validate prompt's arguments in the `handle` method using Laravel's built-in validation features.

```php
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompts\PromptResult;

public function handle(Request $request): Response
{
    $request->validate([
        'location' => 'required|string',
    ]);

    $location = $request->string('location');
}
```

## Registering Servers

The easiest way to register MCP servers is by publishing the `routes/ai.php` file included with the package. If this file exists, the package will automatically load any servers registered via the `Mcp` facade. You can expose a server over HTTP or make it available locally as an Artisan command.

### Web Servers

To register a web-based MCP server that can be accessed via HTTP POST requests, you should use the `web` method:

```php
use App\Mcp\Servers\WeatherExample;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/weather', WeatherExample::class);
```

This will make `WeatherExample` available at the `/mcp/weather` endpoint.

### Local Servers

To register a local MCP server that can be run as an Artisan command:

```php
use App\Mcp\Servers\WeatherExample;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('demo', WeatherExample::class);
```

This makes the server available via the `mcp:start` Artisan command:

```bash
php artisan mcp:start demo
```

## Authentication

Web-based MCP servers can be protected using [Laravel Passport](https://laravel.com/docs/passport), turning your MCP server into an OAuth2 protected resource.

If you already have Passport set up for your app, all you need to do is add the `Mcp::oauthRoutes()` helper to your `routes/web.php` file. This registers the required OAuth2 discovery and client registration endpoints. The method accepts an optional route prefix, which defaults to `oauth`.

```php
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();
```

Then, apply the `auth:api` middleware to your server registration in `routes/ai.php`:

```php
use App\Mcp\Servers\WeatherExample;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/weather', WeatherExample::class)
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
