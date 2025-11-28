<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Contracts;

use Laravel\Mcp\Support\UriTemplate;

interface HasUriTemplate
{
    /**
     * Get the URI pattern for the resource template.
     */
    public function uriTemplate(): UriTemplate;
}
