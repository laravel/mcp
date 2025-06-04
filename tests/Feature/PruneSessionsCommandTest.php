<?php

namespace Laravel\Mcp\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Mcp\Session\Session;
use Laravel\Mcp\Tests\TestCase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;

class PruneSessionsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function createSession(Carbon $createdAt): Session
    {
        $session = Session::create([
            'id' => str()->uuid(),
            'payload' => [],
        ]);

        $session->created_at = $createdAt;
        $session->save();

        return $session;
    }

    #[Test]
    public function it_exits_with_error_if_expiration_is_not_configured(): void
    {
        Config::set('mcp.session.expiration', null);

        $this->artisan('mcp:prune-sessions')
            ->expectsOutput('MCP session expiration is not configured. Please set mcp.session.expiration in your config.')
            ->assertFailed();
    }

    #[Test]
    public function it_exits_with_error_if_expiration_is_invalid(): void
    {
        Config::set('mcp.session.expiration', 'invalid');

        $this->artisan('mcp:prune-sessions')
            ->expectsOutput('MCP session expiration value must be a positive number of minutes.')
            ->assertFailed();

        Config::set('mcp.session.expiration', 0);

        $this->artisan('mcp:prune-sessions')
            ->expectsOutput('MCP session expiration value must be a positive number of minutes.')
            ->assertFailed();

        Config::set('mcp.session.expiration', -10);

        $this->artisan('mcp:prune-sessions')
            ->expectsOutput('MCP session expiration value must be a positive number of minutes.')
            ->assertFailed();
    }

    #[Test]
    public function it_prunes_old_sessions_and_keeps_new_ones(): void
    {
        $expirationMinutes = 60;

        Config::set('mcp.session.expiration', $expirationMinutes);

        $oldSession1 = $this->createSession(Carbon::now()->subMinutes($expirationMinutes + 10));
        $oldSession2 = $this->createSession(Carbon::now()->subMinutes($expirationMinutes + 5));

        $mediumSession = $this->createSession(Carbon::now()->subMinutes($expirationMinutes));

        $newSession1 = $this->createSession(Carbon::now()->subMinutes($expirationMinutes - 5));
        $newSession2 = $this->createSession(Carbon::now());

        $cutOffTime = Carbon::now()->subMinutes($expirationMinutes)->toDateTimeString();

        $this->artisan('mcp:prune-sessions')
            ->expectsOutput("Successfully pruned 3 MCP session(s) older than {$cutOffTime}.")
            ->assertSuccessful();

        $this->assertModelMissing($oldSession1);
        $this->assertModelMissing($oldSession2);
        $this->assertModelMissing($mediumSession);
        $this->assertModelExists($newSession1);
        $this->assertModelExists($newSession2);
    }

    #[Test]
    public function it_reports_zero_when_no_sessions_are_pruned(): void
    {
        $expirationMinutes = 60;

        Config::set('mcp.session.expiration', $expirationMinutes);

        $this->createSession(Carbon::now()->subMinutes($expirationMinutes - 5));
        $this->createSession(Carbon::now());

        $cutOffTime = Carbon::now()->subMinutes($expirationMinutes)->toDateTimeString();

        $this->artisan('mcp:prune-sessions')
            ->expectsOutput("Successfully pruned 0 MCP session(s) older than {$cutOffTime}.")
            ->assertSuccessful();

        $this->assertEquals(2, Session::count());
    }
}
