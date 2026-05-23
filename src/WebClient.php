<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Laravel\Mcp\Client\Transport\HttpTransport;
use Laravel\Mcp\Schema\Implementation;

class WebClient extends Client
{
    public function __construct(
        protected HttpTransport $httpTransport,
        ?Implementation $clientInfo = null,
    ) {
        parent::__construct($httpTransport, $clientInfo);
    }

    public function withToken(string $token): static
    {
        $this->httpTransport->withToken($token);

        return $this;
    }
}
