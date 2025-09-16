<?php

declare(strict_types=1);

namespace Tests\Unit\Transport;

use Illuminate\Http\Response;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tests\TestCase;

class FakeTransporterTest extends TestCase
{
    public function test_it_implements_transport_interface(): void
    {
        $transporter = new FakeTransporter;

        $this->assertInstanceOf(\Laravel\Mcp\Server\Contracts\Transport::class, $transporter);
    }

    public function test_it_can_receive_handler(): void
    {
        $transporter = new FakeTransporter;
        $called = false;

        $transporter->onReceive(function (string $message) use (&$called): void {
            $called = true;
        });

        $response = $transporter->run();

        $this->assertTrue($called);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_run_returns_json_response(): void
    {
        $transporter = new FakeTransporter;

        $response = $transporter->run();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
        $this->assertEquals('', $response->getContent());
    }

    public function test_run_calls_handler_with_empty_string(): void
    {
        $transporter = new FakeTransporter;
        $receivedMessage = null;

        $transporter->onReceive(function (string $message) use (&$receivedMessage): void {
            $receivedMessage = $message;
        });

        $transporter->run();

        $this->assertEquals('', $receivedMessage);
    }

    public function test_session_id_returns_unique_string(): void
    {
        $transporter = new FakeTransporter;

        $sessionId1 = $transporter->sessionId();
        $sessionId2 = $transporter->sessionId();

        $this->assertIsString($sessionId1);
        $this->assertIsString($sessionId2);
        $this->assertNotEquals($sessionId1, $sessionId2);
    }

    public function test_send_does_nothing(): void
    {
        $transporter = new FakeTransporter;

        $transporter->send('test message');
        $transporter->send('test message', 'session-id');

        $this->assertTrue(true);
    }

    public function test_stream_does_nothing(): void
    {
        $transporter = new FakeTransporter;

        $transporter->stream(fn (): string => 'test');

        $this->assertTrue(true);
    }
}
