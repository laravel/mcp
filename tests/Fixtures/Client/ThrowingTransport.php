<?php

declare(strict_types=1);

namespace Tests\Fixtures\Client;

use Laravel\Mcp\Exceptions\ClientException;

class ThrowingTransport extends FakeTransport
{
    public function disconnect(): never
    {
        throw new ClientException('disconnect failed');
    }
}
