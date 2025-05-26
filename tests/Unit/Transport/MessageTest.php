<?php

namespace Tests\Unit\Transport;

use Laravel\Mcp\Transport\Message;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated()
    {
        $message = new Message(1, ['foo' => 'bar']);

        $this->assertSame(1, $message->id);
        $this->assertSame(['foo' => 'bar'], $message->params);
    }
}
