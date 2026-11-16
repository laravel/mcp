<?php

declare(strict_types=1);

use Laravel\Mcp\Server\Skills\SkillCatalog;
use Tests\Fixtures\ConflictingSkillsServer;
use Tests\Fixtures\ExampleServer;
use Tests\Fixtures\InvalidSkillsServer;
use Tests\Fixtures\SkillsServer;

beforeEach(function (): void {
    SkillCatalog::flushState();
});

it('lists the valid skills of a server', function (): void {
    $this->artisan('mcp:skills', ['server' => SkillsServer::class])
        ->expectsOutputToContain('skill://release-checklist/SKILL.md')
        ->expectsOutputToContain('1 skill valid.')
        ->assertExitCode(0);
});

it('reports a server without skills', function (): void {
    $this->artisan('mcp:skills', ['server' => ExampleServer::class])
        ->expectsOutputToContain('0 skills valid.')
        ->assertExitCode(0);
});

it('fails with the reason when a skill is invalid', function (): void {
    $this->artisan('mcp:skills', ['server' => InvalidSkillsServer::class])
        ->expectsOutputToContain('skill://release-checklist/SKILL.md')
        ->expectsOutputToContain('missing-skill] is invalid.')
        ->assertExitCode(1);
});

it('fails when skills conflict', function (): void {
    $this->artisan('mcp:skills', ['server' => ConflictingSkillsServer::class])
        ->expectsOutputToContain('Duplicate server skill URI')
        ->assertExitCode(1);
});

it('fails for unknown servers', function (): void {
    $this->artisan('mcp:skills', ['server' => 'MissingServer'])
        ->expectsOutputToContain('MCP server [MissingServer] not found.')
        ->assertExitCode(1);
});
