<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Enums\Role;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ResourceLinkTool extends Tool
{
    protected string $description = 'Returns a resource link to a generated report.';

    public function handle(Request $request): Response
    {
        return Response::resourceLink(
            uri: 'file:///reports/monthly.pdf',
            name: 'monthly-report',
            title: 'Monthly Report',
            description: 'Sales rollup by region.',
            mimeType: 'application/pdf',
            size: 2048,
            audience: [Role::User],
            priority: 0.9,
            lastModified: '2026-05-07T12:00:00Z',
        );
    }
}
