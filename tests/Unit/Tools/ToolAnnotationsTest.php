<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\Role;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Annotations\Audience;
use Laravel\Mcp\Server\Annotations\Priority;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

it('accepts valid tool annotations', function (): void {
    $tool = new #[IsReadOnly] class extends Tool
    {
        public function handle(Request $request): Response
        {
            return Response::text('test');
        }
    };

    $annotations = $tool->annotations();

    expect($annotations)->toHaveKey('readOnlyHint')
        ->and($annotations['readOnlyHint'])->toBeTrue();
});

it('rejects resource annotations on tools', function (): void {
    expect(function (): void {
        $tool = new #[Audience(Role::Assistant)] class extends Tool
        {
            public function handle(Request $request): Response
            {
                return Response::text('test');
            }
        };

        $tool->annotations();
    })->toThrow(InvalidArgumentException::class, 'Annotation [Laravel\Mcp\Server\Annotations\Audience] cannot be used on');
});

it('rejects priority annotation on tools', function (): void {
    expect(function (): void {
        $tool = new #[Priority(0.8)] class extends Tool
        {
            public function handle(Request $request): Response
            {
                return Response::text('test');
            }
        };

        $tool->annotations();
    })->toThrow(InvalidArgumentException::class, 'Annotation [Laravel\Mcp\Server\Annotations\Priority] cannot be used on');
});

it('accepts multiple tool annotations', function (): void {
    $tool = new #[IsReadOnly]
    #[IsIdempotent] class extends Tool
    {
        public function handle(Request $request): Response
        {
            return Response::text('test');
        }
    };

    $annotations = $tool->annotations();

    expect($annotations)->toHaveKey('readOnlyHint')
        ->and($annotations)->toHaveKey('idempotentHint')
        ->and($annotations['readOnlyHint'])->toBeTrue()
        ->and($annotations['idempotentHint'])->toBeTrue();
});
