<?php

declare(strict_types=1);

namespace Tests\Conformance;

use Laravel\Mcp\Server;
use Tests\Conformance\Prompts\PromptWithArguments;
use Tests\Conformance\Prompts\PromptWithEmbeddedResource;
use Tests\Conformance\Prompts\PromptWithImage;
use Tests\Conformance\Prompts\SimplePrompt;
use Tests\Conformance\Resources\StaticBinaryResource;
use Tests\Conformance\Resources\StaticTextResource;
use Tests\Conformance\Resources\TemplateResource;
use Tests\Conformance\Resources\WatchedResource;
use Tests\Conformance\Tools\AudioContentTool;
use Tests\Conformance\Tools\EmbeddedResourceTool;
use Tests\Conformance\Tools\ErrorHandlingTool;
use Tests\Conformance\Tools\ImageContentTool;
use Tests\Conformance\Tools\MultipleContentTypesTool;
use Tests\Conformance\Tools\SimpleTextTool;

class ConformanceServer extends Server
{
    protected string $name = 'mcp-conformance-test-server';

    protected string $version = '1.0.0';

    protected array $supportedProtocolVersion = [
        '2026-07-28',
        '2025-11-25',
        '2025-06-18',
    ];

    public array $tools = [
        SimpleTextTool::class,
        ImageContentTool::class,
        AudioContentTool::class,
        EmbeddedResourceTool::class,
        MultipleContentTypesTool::class,
        ErrorHandlingTool::class,
    ];

    public array $resources = [
        StaticTextResource::class,
        StaticBinaryResource::class,
        TemplateResource::class,
        WatchedResource::class,
    ];

    public array $prompts = [
        SimplePrompt::class,
        PromptWithArguments::class,
        PromptWithEmbeddedResource::class,
        PromptWithImage::class,
    ];
}
