<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Transport\Eras;

use Illuminate\Http\Client\Response as ClientResponse;
use Laravel\Mcp\Enums\ProtocolVersion;

interface Era
{
    public function speaking(ProtocolVersion $version): Era;

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, string>
     */
    public function headers(array $body): array;

    public function inspect(ClientResponse $response): void;

    public function hasSession(): bool;
}
