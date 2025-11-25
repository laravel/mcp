<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Contracts;

use Laravel\Mcp\Support\UriTemplate;

interface SupportsURITemplate
{
    public function uriTemplate(): UriTemplate;
}
