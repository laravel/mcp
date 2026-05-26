<?php

declare(strict_types=1);

namespace Tests\Fixtures\Client;

use RuntimeException;

class ThrowingTransport extends FakeTransport
{
    public function disconnect(): never
    {
        throw new RuntimeException('disconnect failed');
    }
}
