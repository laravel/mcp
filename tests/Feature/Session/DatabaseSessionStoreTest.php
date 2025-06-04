<?php

namespace Laravel\Mcp\Tests\Feature\Session;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Session\DatabaseSessionStore;
use Laravel\Mcp\SessionContext;
use Laravel\Mcp\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DatabaseSessionStoreTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_put_and_get_session_context()
    {
        $id = 'session_id';
        $context = new SessionContext(
            supportedProtocolVersions: ['2025-03-26'],
            serverCapabilities: ['tools' => ['listChanged' => false]],
            serverName: 'Test Server',
            serverVersion: '1.0.0',
            instructions: 'Test Instructions',
            tools: [],
            maxPaginationLength: 100,
            defaultPaginationLength: 10
        );

        $store = new DatabaseSessionStore();

        $store->put($id, $context);

        $this->assertDatabaseHas('mcp_sessions', [
            'id' => $id,
            'payload' => json_encode($context->toArray()), // Assuming payload is stored as JSON
        ]);

        $retrievedContext = $store->get($id);

        $this->assertEquals($context, $retrievedContext);
    }

    #[Test]
    public function get_returns_null_if_session_not_found()
    {
        $id = 'non_existent_session_id';

        $store = new DatabaseSessionStore();
        $retrievedContext = $store->get($id);

        $this->assertNull($retrievedContext);
        $this->assertDatabaseMissing('mcp_sessions', [
            'id' => $id,
        ]);
    }
}
