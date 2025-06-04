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
        $session = new SessionContext(
            clientCapabilities: [],
        );

        $store = new DatabaseSessionStore();

        $store->put($id, $session);

        $this->assertDatabaseHas('mcp_sessions', [
            'id' => $id,
            'payload' => json_encode($session->toArray()), // Assuming payload is stored as JSON
        ]);

        $retrievedSession = $store->get($id);

        $this->assertEquals($session, $retrievedSession);
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
