<?php

declare(strict_types=1);

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;
use Tests\Fixtures\ArrayTransport;

it('resolves name from attribute on a tool', function (): void {
    $tool = new AttributeNameTool;

    expect($tool->name())->toBe('custom-tool-name');
});

it('resolves title from attribute on a tool', function (): void {
    $tool = new AttributeTitleTool;

    expect($tool->title())->toBe('My Custom Title');
});

it('resolves description from attribute on a tool', function (): void {
    $tool = new AttributeDescriptionTool;

    expect($tool->description())->toBe('A tool configured via attribute');
});

it('prefers attribute over property for name', function (): void {
    $tool = new AttributeOverridesPropertyNameTool;

    expect($tool->name())->toBe('attribute-name');
});

it('prefers attribute over property for title', function (): void {
    $tool = new AttributeOverridesPropertyTitleTool;

    expect($tool->title())->toBe('Attribute Title');
});

it('prefers attribute over property for description', function (): void {
    $tool = new AttributeOverridesPropertyDescriptionTool;

    expect($tool->description())->toBe('Attribute description');
});

it('falls back to property when no attribute for name', function (): void {
    $tool = new PropertyOnlyNameTool;

    expect($tool->name())->toBe('property-name');
});

it('falls back to auto-generated when no attribute or property for name', function (): void {
    $tool = new FallbackNameTool;

    expect($tool->name())->toBe('fallback-name-tool');
});

it('resolves name from attribute on a resource', function (): void {
    $resource = new AttributeNameResource;

    expect($resource->name())->toBe('my-resource');
});

it('resolves uri from attribute on a resource', function (): void {
    $resource = new AttributeUriResource;

    expect($resource->uri())->toBe('file://custom/path');
});

it('resolves mime type from attribute on a resource', function (): void {
    $resource = new AttributeMimeTypeResource;

    expect($resource->mimeType())->toBe('application/json');
});

it('prefers attribute over property for uri', function (): void {
    $resource = new AttributeOverridesPropertyUriResource;

    expect($resource->uri())->toBe('file://attribute/uri');
});

it('prefers attribute over property for mime type', function (): void {
    $resource = new AttributeOverridesPropertyMimeTypeResource;

    expect($resource->mimeType())->toBe('application/xml');
});

it('falls back to property when no attribute for uri', function (): void {
    $resource = new PropertyOnlyUriResource;

    expect($resource->uri())->toBe('file://property/uri');
});

it('falls back to auto-generated when no attribute or property for uri', function (): void {
    $resource = new FallbackUriResource;

    expect($resource->uri())->toBe('file://resources/fallback-uri-resource');
});

it('falls back to text/plain when no attribute or property for mime type', function (): void {
    $resource = new FallbackMimeTypeResource;

    expect($resource->mimeType())->toBe('text/plain');
});

it('resolves name from attribute on a prompt', function (): void {
    $prompt = new AttributeNamePrompt;

    expect($prompt->name())->toBe('my-prompt');
});

it('resolves description from attribute on a prompt', function (): void {
    $prompt = new AttributeDescriptionPrompt;

    expect($prompt->description())->toBe('A prompt via attribute');
});

it('resolves server name from attribute', function (): void {
    $transport = new ArrayTransport;
    $server = new AttributeNameServer($transport);

    $context = $server->createContext();

    expect($context->serverName)->toBe('Attribute Server');
});

it('resolves server version from attribute', function (): void {
    $transport = new ArrayTransport;
    $server = new AttributeVersionServer($transport);

    $context = $server->createContext();

    expect($context->serverVersion)->toBe('2.0.0');
});

it('resolves server instructions from attribute', function (): void {
    $transport = new ArrayTransport;
    $server = new AttributeInstructionsServer($transport);

    $context = $server->createContext();

    expect($context->instructions)->toBe('Custom instructions via attribute');
});

it('prefers attribute over property for server name', function (): void {
    $transport = new ArrayTransport;
    $server = new AttributeOverridesPropertyNameServer($transport);

    $context = $server->createContext();

    expect($context->serverName)->toBe('Attribute Server Name');
});

it('falls back to property when no attribute for server name', function (): void {
    $transport = new ArrayTransport;
    $server = new PropertyOnlyNameServer($transport);

    $context = $server->createContext();

    expect($context->serverName)->toBe('Property Server');
});

it('includes attributes in toArray output for tools', function (): void {
    $tool = new AttributeDescriptionTool;
    $array = $tool->toArray();

    expect($array['description'])->toBe('A tool configured via attribute');
});

it('includes attributes in toArray output for resources', function (): void {
    $resource = new AttributeUriResource;
    $array = $resource->toArray();

    expect($array['uri'])->toBe('file://custom/path');
});

it('includes attributes in toArray output for prompts', function (): void {
    $prompt = new AttributeDescriptionPrompt;
    $array = $prompt->toArray();

    expect($array['description'])->toBe('A prompt via attribute');
});

it('ignores uri attribute when resource implements HasUriTemplate', function (): void {
    $resource = new UriAttributeIgnoredForTemplateResource;

    expect($resource->uri())->toBe('file://users/{userId}');
});

it('resolves multiple attributes on a single class', function (): void {
    $tool = new MultipleAttributesTool;

    expect($tool->name())->toBe('multi-tool')
        ->and($tool->title())->toBe('Multi Tool')
        ->and($tool->description())->toBe('A tool with all attributes');
});

it('inherits attribute from parent class', function (): void {
    $tool = new ChildToolWithoutAttribute;

    expect($tool->name())->toBe('parent-tool-name');
});

it('child attribute overrides parent attribute', function (): void {
    $tool = new ChildToolWithOverride;

    expect($tool->name())->toBe('child-tool-name');
});

it('inherits attribute from parent server class', function (): void {
    $transport = new ArrayTransport;
    $server = new ChildServerWithoutAttribute($transport);

    $context = $server->createContext();

    expect($context->serverName)->toBe('Parent Server');
});

it('child server attribute overrides parent server attribute', function (): void {
    $transport = new ArrayTransport;
    $server = new ChildServerWithOverride($transport);

    $context = $server->createContext();

    expect($context->serverName)->toBe('Child Server');
});

#[Name('parent-tool-name')]
class ParentToolWithAttribute extends Tool
{
    public function handle(): Response
    {
        return Response::text('test');
    }
}

class ChildToolWithoutAttribute extends ParentToolWithAttribute {}

#[Name('child-tool-name')]
class ChildToolWithOverride extends ParentToolWithAttribute {}

#[Name('Parent Server')]
class ParentServerWithAttribute extends \Laravel\Mcp\Server
{
    protected function generateSessionId(): string
    {
        return 'test-session';
    }
}

class ChildServerWithoutAttribute extends ParentServerWithAttribute {}

#[Name('Child Server')]
class ChildServerWithOverride extends ParentServerWithAttribute {}

#[Name('custom-tool-name')]
class AttributeNameTool extends Tool
{
    public function handle(): Response
    {
        return Response::text('test');
    }
}

#[Title('My Custom Title')]
class AttributeTitleTool extends Tool
{
    public function handle(): Response
    {
        return Response::text('test');
    }
}

#[Description('A tool configured via attribute')]
class AttributeDescriptionTool extends Tool
{
    public function handle(): Response
    {
        return Response::text('test');
    }
}

#[Name('attribute-name')]
class AttributeOverridesPropertyNameTool extends Tool
{
    protected string $name = 'property-name';

    public function handle(): Response
    {
        return Response::text('test');
    }
}

#[Title('Attribute Title')]
class AttributeOverridesPropertyTitleTool extends Tool
{
    protected string $title = 'Property Title';

    public function handle(): Response
    {
        return Response::text('test');
    }
}

#[Description('Attribute description')]
class AttributeOverridesPropertyDescriptionTool extends Tool
{
    protected string $description = 'Property description';

    public function handle(): Response
    {
        return Response::text('test');
    }
}

class PropertyOnlyNameTool extends Tool
{
    protected string $name = 'property-name';

    public function handle(): Response
    {
        return Response::text('test');
    }
}

class FallbackNameTool extends Tool
{
    public function handle(): Response
    {
        return Response::text('test');
    }
}

#[Name('my-resource')]
class AttributeNameResource extends Resource
{
    public function handle(): Response
    {
        return Response::text('content');
    }
}

#[Uri('file://custom/path')]
class AttributeUriResource extends Resource
{
    public function handle(): Response
    {
        return Response::text('content');
    }
}

#[MimeType('application/json')]
class AttributeMimeTypeResource extends Resource
{
    public function handle(): Response
    {
        return Response::text('content');
    }
}

#[Uri('file://attribute/uri')]
class AttributeOverridesPropertyUriResource extends Resource
{
    protected string $uri = 'file://property/uri';

    public function handle(): Response
    {
        return Response::text('content');
    }
}

#[MimeType('application/xml')]
class AttributeOverridesPropertyMimeTypeResource extends Resource
{
    protected string $mimeType = 'text/html';

    public function handle(): Response
    {
        return Response::text('content');
    }
}

class PropertyOnlyUriResource extends Resource
{
    protected string $uri = 'file://property/uri';

    public function handle(): Response
    {
        return Response::text('content');
    }
}

class FallbackUriResource extends Resource
{
    public function handle(): Response
    {
        return Response::text('content');
    }
}

class FallbackMimeTypeResource extends Resource
{
    public function handle(): Response
    {
        return Response::text('content');
    }
}

#[Name('my-prompt')]
class AttributeNamePrompt extends Prompt
{
    public function handle(): Response
    {
        return Response::text('prompt content');
    }
}

#[Description('A prompt via attribute')]
class AttributeDescriptionPrompt extends Prompt
{
    public function handle(): Response
    {
        return Response::text('prompt content');
    }
}

#[Name('Attribute Server')]
class AttributeNameServer extends \Laravel\Mcp\Server
{
    protected function generateSessionId(): string
    {
        return 'test-session';
    }
}

#[Version('2.0.0')]
class AttributeVersionServer extends \Laravel\Mcp\Server
{
    protected function generateSessionId(): string
    {
        return 'test-session';
    }
}

#[Instructions('Custom instructions via attribute')]
class AttributeInstructionsServer extends \Laravel\Mcp\Server
{
    protected function generateSessionId(): string
    {
        return 'test-session';
    }
}

#[Name('Attribute Server Name')]
class AttributeOverridesPropertyNameServer extends \Laravel\Mcp\Server
{
    protected string $name = 'Property Server Name';

    protected function generateSessionId(): string
    {
        return 'test-session';
    }
}

class PropertyOnlyNameServer extends \Laravel\Mcp\Server
{
    protected string $name = 'Property Server';

    protected function generateSessionId(): string
    {
        return 'test-session';
    }
}

#[Uri('file://ignored/uri')]
class UriAttributeIgnoredForTemplateResource extends Resource implements \Laravel\Mcp\Server\Contracts\HasUriTemplate
{
    public function uriTemplate(): \Laravel\Mcp\Support\UriTemplate
    {
        return new \Laravel\Mcp\Support\UriTemplate('file://users/{userId}');
    }

    public function handle(): Response
    {
        return Response::text('content');
    }
}

#[Name('multi-tool')]
#[Title('Multi Tool')]
#[Description('A tool with all attributes')]
class MultipleAttributesTool extends Tool
{
    public function handle(): Response
    {
        return Response::text('test');
    }
}
