<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Contracts;

use Laravel\Mcp\Support\UriTemplate;

interface SupportsUriTemplate
{
    public function uriTemplate(): UriTemplate;
}
