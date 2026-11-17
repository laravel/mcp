<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Mcp\Enums\CacheScope;
use Laravel\Mcp\Enums\Extension;
use Laravel\Mcp\Exceptions\InvalidSkillException;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Cacheable;
use Laravel\Mcp\Server\Skill;
use Laravel\Mcp\Server\Skills\SkillCatalog;
use Laravel\Mcp\Server\Skills\SkillDirectory;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Testing\TestSkillResponse;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tests\Fixtures\ArrayTransport;
use Tests\Fixtures\DirectorySkill;
use Tests\Fixtures\EmptySkillListing;
use Tests\Fixtures\ExampleServer;
use Tests\Fixtures\HouseStyleSkill;
use Tests\Fixtures\ReleaseChecklistSkill;
use Tests\Fixtures\SkillsServer;
use Tests\Fixtures\TeamReleaseChecklistSkill;

function skillResponse(Server $server, ArrayTransport $transport, string $method, array $params = []): array
{
    $server->handle(json_encode([
        'jsonrpc' => '2.0',
        'id' => 42,
        'method' => $method,
        'params' => ['_meta' => protocolMeta(), ...$params],
    ]));

    return json_decode(end($transport->sent), true);
}

function fixtureSkillPath(): string
{
    return __DIR__.'/../../Fixtures/Skills/release-checklist';
}

beforeEach(function (): void {
    SkillCatalog::flushState();
    SkillDirectory::flushDigests();

    $this->transport = new ArrayTransport;
    $this->server = new SkillsServer($this->transport);
    $this->temporary = sys_get_temp_dir().'/mcp-skills-'.bin2hex(random_bytes(8));
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->temporary);
});

it('declares the extension and resources in discovery without optional features', function (): void {
    $server = new class($this->transport) extends SkillsServer
    {
        protected array $capabilities = [];

        protected array $extensions = [Extension::Ui];
    };
    $server->start();

    $response = skillResponse($server, $this->transport, 'server/discover');
    expect($response['result']['capabilities'])->toHaveKey('resources')
        ->and($response['result']['capabilities']['extensions'])->toBe([
            'io.modelcontextprotocol/ui' => [],
            'io.modelcontextprotocol/skills' => [],
        ])
        ->and(json_decode($this->transport->sent[0])->result->capabilities->extensions->{'io.modelcontextprotocol/skills'})->toBeInstanceOf(stdClass::class)
        ->and(json_decode($this->transport->sent[0])->result->capabilities->resources)->toBeInstanceOf(stdClass::class);
});

it('leaves servers without skills unchanged', function (): void {
    $server = new ExampleServer($this->transport);
    $server->start();

    expect(skillResponse($server, $this->transport, 'server/discover')['result']['capabilities'])->not->toHaveKey('extensions');
    expect(skillResponse($server, $this->transport, 'skills/list')['error']['code'])->toBe(-32601);
});

it('registers skills from the skills property', function (string $class, string $uri): void {
    $this->server->skills = [$class];
    $this->server->start();

    expect(skillResponse($this->server, $this->transport, 'server/discover')['result']['capabilities']['extensions'])
        ->toHaveKey('io.modelcontextprotocol/skills');

    $entry = skillResponse($this->server, $this->transport, 'skills/list')['result']['skills'][0];
    expect($entry['uri'])->toBe($uri)
        ->and(skillResponse($this->server, $this->transport, 'skills/get', ['uri' => $entry['uri']])['result']['skill'])->toBe($entry);

    foreach ($entry['resources'] as $resource) {
        $content = skillResponse($this->server, $this->transport, 'resources/read', ['uri' => $resource['uri']])['result']['contents'][0];
        expect(strlen($content['text']))->toBe($resource['size'])
            ->and('sha256:'.hash('sha256', $content['text']))->toBe($resource['digest']);
    }
})->with([
    [ReleaseChecklistSkill::class, 'skill://release-checklist/SKILL.md'],
    [TeamReleaseChecklistSkill::class, 'skill://team/release-checklist/SKILL.md'],
]);

it('serves skill classes registered by class name', function (): void {
    $this->server->skills = [HouseStyleSkill::class, TeamReleaseChecklistSkill::class];
    $this->server->start();

    $skills = skillResponse($this->server, $this->transport, 'skills/list')['result']['skills'];
    $content = skillResponse($this->server, $this->transport, 'resources/read', ['uri' => 'skill://house-style/SKILL.md'])['result']['contents'][0];

    expect(array_column($skills, 'uri'))->toBe(['skill://house-style/SKILL.md', 'skill://team/release-checklist/SKILL.md'])
        ->and($skills[0]['frontmatter'])->toBe(['name' => 'house-style', 'description' => 'Use when you write copy for a customer.', 'license' => 'MIT'])
        ->and($content['text'])->toEndWith("---\n\n# House Style\n\nBe brief.\n")
        ->and(skillResponse($this->server, $this->transport, 'resources/read', ['uri' => 'skill://team/release-checklist/references/checklist.md'])['result']['contents'][0]['text'])
        ->toContain('Review the changelog');
});

it('applies should register to skill classes and instances', function (Closure $source): void {
    config(['mcp.testing.house-style' => false]);
    $this->server->skills = [$source()];
    $this->server->start();

    expect(skillResponse($this->server, $this->transport, 'skills/list')['result']['skills'])->toBe([])
        ->and(skillResponse($this->server, $this->transport, 'skills/get', ['uri' => 'skill://house-style/SKILL.md'])['error']['code'])->toBe(-32602)
        ->and(skillResponse($this->server, $this->transport, 'resources/read', ['uri' => 'skill://house-style/SKILL.md'])['error']['code'])->toBe(-32602);
})->with([
    'class name' => [fn (): string => HouseStyleSkill::class],
    'instance' => [fn (): Skill => new HouseStyleSkill],
]);

it('resolves relative skill paths from the resources path', function (): void {
    $files = new Filesystem;
    $files->copyDirectory(fixtureSkillPath(), resource_path('skills/release-checklist'));

    try {
        $this->server->skills = [new DirectorySkill('skills/release-checklist', 'skill://copy/release-checklist/SKILL.md')];
        $this->server->start();

        expect(array_column(skillResponse($this->server, $this->transport, 'skills/list')['result']['skills'], 'uri'))
            ->toBe(['skill://copy/release-checklist/SKILL.md'])
            ->and((new DirectorySkill('skills/release-checklist'))->directory()->path)->toBe(resource_path('skills/release-checklist'))
            ->and((new DirectorySkill('/absolute/path'))->directory()->path)->toBe('/absolute/path');
    } finally {
        $files->deleteDirectory(resource_path('skills'));
    }
});

it('rejects unsupported skill sources', function (mixed $source): void {
    $this->server->skills = [$source];
    expect(fn () => $this->server->createContext()->skills())->toThrow(InvalidArgumentException::class, 'Skill sources must be Skill classes or instances.');
})->with([123, 'skills/release-checklist', ExampleServer::class]);

it('can declare an empty skill catalog explicitly', function (): void {
    $server = new class($this->transport) extends Server
    {
        protected array $extensions = [Extension::Skills];
    };
    $server->start();
    expect(skillResponse($server, $this->transport, 'skills/list')['result']['skills'])->toBe([])
        ->and(skillResponse($server, $this->transport, 'skills/get', ['uri' => 'skill://unknown/SKILL.md'])['error']['code'])->toBe(-32602);
});

it('declares the skills extension once when it is also declared explicitly', function (): void {
    $server = new class($this->transport) extends Server
    {
        protected array $extensions = [Extension::Skills];

        protected array $skills = [ReleaseChecklistSkill::class];
    };
    $server->start();

    expect((fn (): array => $this->extensions)->call($server))->toBe([Extension::Skills]);
});

it('returns identical complete entries from list and get with required cache fields', function (): void {
    $this->server->start();
    $list = skillResponse($this->server, $this->transport, 'skills/list')['result'];
    $get = skillResponse($this->server, $this->transport, 'skills/get', ['uri' => 'skill://release-checklist/SKILL.md'])['result'];

    foreach ([$list, $get] as $result) {
        expect($result['resultType'])->toBe('complete')
            ->and($result['ttlMs'])->toBe(0)
            ->and($result['cacheScope'])->toBe('private');
    }

    expect($get['skill'])->toBe($list['skills'][0])
        ->and($get['skill']['resources'])->toHaveCount(2);
});

it('uses existing server cache configuration for skill methods', function (): void {
    $server = new #[Cacheable(ttlMs: 60000, scope: CacheScope::Public)] class($this->transport) extends SkillsServer
    {
        protected function cacheHints(): array
        {
            return ['skills/get' => new Cacheable(ttlMs: 1000)];
        }
    };
    $server->start();
    expect(skillResponse($server, $this->transport, 'skills/list')['result'])->ttlMs->toBe(60000)->cacheScope->toBe('public')
        ->and(skillResponse($server, $this->transport, 'skills/get', ['uri' => 'skill://release-checklist/SKILL.md'])['result'])->ttlMs->toBe(1000)->cacheScope->toBe('private');
});

it('paginates atomic skills and does not identify them by name', function (): void {
    $this->server->defaultPaginationLength = 1;
    $this->server->skills = [
        new DirectorySkill(fixtureSkillPath(), 'skill://first/release-checklist/SKILL.md'),
        new DirectorySkill(fixtureSkillPath(), 'skill://second/release-checklist/SKILL.md'),
    ];
    $this->server->start();

    $first = skillResponse($this->server, $this->transport, 'skills/list')['result'];
    $second = skillResponse($this->server, $this->transport, 'skills/list', ['cursor' => $first['nextCursor']])['result'];

    expect($first['skills'])->toHaveCount(1)
        ->and($first['skills'][0]['resources'])->toHaveCount(2)
        ->and($second['skills'])->toHaveCount(1)
        ->and($second['skills'][0]['resources'])->toHaveCount(2)
        ->and($first['skills'][0]['uri'])->not->toBe($second['skills'][0]['uri'])
        ->and($first['skills'][0]['frontmatter']['name'])->toBe($second['skills'][0]['frontmatter']['name'])
        ->and($second)->not->toHaveKey('nextCursor');

    expect(skillResponse($this->server, $this->transport, 'skills/get', ['uri' => $second['skills'][0]['uri']])['result']['skill'])->toBe($second['skills'][0]);
});

it('can retrieve registered skills omitted by a partial list handler', function (): void {
    $server = new class($this->transport) extends SkillsServer
    {
        protected function boot(): void
        {
            $this->addMethod('skills/list', EmptySkillListing::class);
        }
    };
    $server->start();
    expect(skillResponse($server, $this->transport, 'skills/list')['result']['skills'])->toBe([])
        ->and(skillResponse($server, $this->transport, 'skills/get', ['uri' => 'skill://release-checklist/SKILL.md'])['result']['skill']['uri'])->toBe('skill://release-checklist/SKILL.md');
});

it('returns invalid params for missing malformed and unknown skill URIs', function (mixed $uri): void {
    $this->server->start();
    $response = skillResponse($this->server, $this->transport, 'skills/get', ['uri' => $uri]);
    expect($response['id'])->toBe(42)->and($response['error']['code'])->toBe(-32602);
})->with([null, '', 123, false, [[]], 'skill://unknown/SKILL.md', 'skill://release-checklist/references/checklist.md']);

it('rejects unregistered and noncanonical file URIs', function (string $uri): void {
    $this->server->start();
    expect(skillResponse($this->server, $this->transport, 'resources/read', ['uri' => $uri])['error']['code'])->toBe(-32602);
})->with([
    'skill://release-checklist/../secret',
    'skill://release-checklist/%2e%2e/secret',
    'skill://release-checklist/%252e%252e/secret',
    'skill://release-checklist/references%2fchecklist.md',
    'skill://release-checklist/references\\checklist.md',
    'skill://release-checklist/SKILL.md?secret',
    'skill://release-checklist/SKILL.md#secret',
    'skill://release-checklist/references',
    'skill://release-checklist/missing',
    'skill://release-checklist/.env',
    'file:///etc/passwd',
]);

it('lists only SKILL.md files while serving every skill file', function (): void {
    $this->server->start();
    $resources = skillResponse($this->server, $this->transport, 'resources/list')['result']['resources'];
    $uris = array_column($resources, 'uri');

    expect($uris)->toContain('file://resources/last-log-line-resource', 'skill://release-checklist/SKILL.md')
        ->not->toContain('skill://release-checklist/references/checklist.md')
        ->and(skillResponse($this->server, $this->transport, 'resources/read', ['uri' => 'skill://release-checklist/references/checklist.md'])['result']['contents'][0]['text'])->toContain('Review the changelog')
        ->and(skillResponse($this->server, $this->transport, 'resources/read', ['uri' => 'file://resources/last-log-line-resource'])['result']['contents'][0]['text'])->toBe('2025-07-02 12:00:00 Error: Something went wrong.')
        ->and(skillResponse($this->server, $this->transport, 'tools/list')['result']['tools'])->toHaveCount(2);

    $skillResource = collect($resources)->firstWhere('uri', 'skill://release-checklist/SKILL.md');
    expect($skillResource['name'])->toBe('release-checklist')
        ->and($skillResource['description'])->toBe('Prepare a release using the bundled checklist.')
        ->and($skillResource['mimeType'])->toBe('text/markdown');
});

it('does not declare or implement optional directory reading', function (): void {
    $this->server->start();
    expect(skillResponse($this->server, $this->transport, 'resources/directory/read', ['uri' => 'skill://release-checklist'])['error']['code'])->toBe(-32601);
});

it('rejects duplicate skill URIs while allowing equal names', function (): void {
    $this->server->skills = [ReleaseChecklistSkill::class, new DirectorySkill(fixtureSkillPath())];
    expect(fn () => $this->server->createContext()->skills())->toThrow(LogicException::class, 'Duplicate server skill URI');
});

it('rejects registered resources that would shadow skill file bytes', function (): void {
    config(['app.debug' => false]);
    $this->server->resources[] = $this->makeResource('wrong bytes', overrides: ['uri' => 'skill://release-checklist/SKILL.md']);
    $this->server->start();

    expect(fn () => $this->server->createContext()->resources())->toThrow(LogicException::class, 'already registered');

    foreach (['skills/list', 'skills/get'] as $method) {
        $response = skillResponse($this->server, $this->transport, $method, ['uri' => 'skill://release-checklist/SKILL.md']);
        expect($response['error']['code'])->toBe(-32603)
            ->and($response)->not->toHaveKey('result');
    }
});

it('serves a complete discovery get and file read flow over HTTP', function (): void {
    Mcp::web('/skills-mcp', SkillsServer::class);

    $request = function (string $method, array $params = []): array {
        $message = ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => ['_meta' => protocolMeta(), ...$params]];

        return $this->postJson('/skills-mcp', $message, mcpHeaders($message))->assertOk()->json('result');
    };

    expect($request('server/discover')['capabilities']['extensions'])->toHaveKey('io.modelcontextprotocol/skills');
    $entry = $request('skills/get', ['uri' => 'skill://release-checklist/SKILL.md'])['skill'];
    expect($request('skills/list')['skills'][0])->toBe($entry);

    foreach ($entry['resources'] as $resource) {
        $content = $request('resources/read', ['uri' => $resource['uri']])['contents'][0];
        expect($content['uri'])->toBe($resource['uri'])
            ->and(strlen($content['text']))->toBe($resource['size'])
            ->and('sha256:'.hash('sha256', $content['text']))->toBe($resource['digest']);
    }
});

it('serves binary and percent encoded files without transforming their bytes and refreshes manifests', function (): void {
    (new Filesystem)->copyDirectory(fixtureSkillPath(), $this->temporary.'/release-checklist');
    $path = $this->temporary.'/release-checklist';
    $bytes = file_get_contents(__DIR__.'/../../Fixtures/binary.png');
    file_put_contents($path.'/a file%.png', $bytes);
    $this->server->skills = [new DirectorySkill($path)];
    $this->server->start();

    $uri = 'skill://release-checklist/a%20file%25.png';
    $result = skillResponse($this->server, $this->transport, 'resources/read', ['uri' => $uri])['result'];
    expect($result['contents'][0]['mimeType'])->toBe('image/png')
        ->and(base64_decode($result['contents'][0]['blob'], true))->toBe($bytes);

    $first = skillResponse($this->server, $this->transport, 'skills/get', ['uri' => 'skill://release-checklist/SKILL.md'])['result']['skill'];
    $manifest = collect($first['resources'])->firstWhere('uri', $uri);
    expect($manifest['size'])->toBe(strlen($bytes))
        ->and($manifest['digest'])->toBe('sha256:'.hash('sha256', $bytes));
    file_put_contents($path.'/references/checklist.md', "Updated instructions\r\n");
    $second = skillResponse($this->server, $this->transport, 'skills/get', ['uri' => 'skill://release-checklist/SKILL.md'])['result']['skill'];
    expect($second['resources'])->not->toBe($first['resources']);
});

it('skips invalid skills without affecting other skills or resources', function (mixed $source): void {
    config(['app.debug' => false]);
    $this->server->skills[] = $source;
    $this->server->start();

    expect(array_column(skillResponse($this->server, $this->transport, 'skills/list')['result']['skills'], 'uri'))->toBe(['skill://release-checklist/SKILL.md'])
        ->and(skillResponse($this->server, $this->transport, 'skills/get', ['uri' => 'skill://release-checklist/SKILL.md'])['result']['skill']['uri'])->toBe('skill://release-checklist/SKILL.md')
        ->and(skillResponse($this->server, $this->transport, 'resources/read', ['uri' => 'file://resources/last-log-line-resource'])['result']['contents'][0]['text'])->toBe('2025-07-02 12:00:00 Error: Something went wrong.')
        ->and(skillResponse($this->server, $this->transport, 'resources/list')['result']['resources'])->not->toBeEmpty();
})->with([
    'empty path' => [fn (): Skill => new DirectorySkill('')],
    'missing directory' => [fn (): Skill => new DirectorySkill('/nonexistent/skill')],
    'filesystem root' => [fn (): Skill => new DirectorySkill('/')],
]);

it('reports each invalid skill once with its class', function (): void {
    Exceptions::fake();
    $this->server->skills[] = new DirectorySkill('/nonexistent/skill');
    $this->server->start();

    skillResponse($this->server, $this->transport, 'skills/list');
    skillResponse($this->server, $this->transport, 'resources/list');

    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(fn (InvalidSkillException $exception): bool => str_contains($exception->getMessage(), 'The skill ['.DirectorySkill::class.'] is invalid.'));
});

it('reports conflicting skill configuration as an internal error on resource reads', function (): void {
    config(['app.debug' => false]);
    $this->server->skills = [ReleaseChecklistSkill::class, ReleaseChecklistSkill::class];
    $this->server->start();

    $response = skillResponse($this->server, $this->transport, 'resources/read', ['uri' => 'file://resources/last-log-line-resource']);
    expect($response['error']['code'])->toBe(-32603)
        ->and($response['error']['message'])->not->toContain('Duplicate');
});

it('retrieves skills and reads their files through the testing helpers', function (): void {
    SkillsServer::skill('skill://release-checklist/SKILL.md')
        ->assertOk()
        ->assertName('release-checklist')
        ->assertDescription('Prepare a release using the bundled checklist.')
        ->assertFrontmatter(['license' => 'MIT', 'metadata' => ['version' => '1.0']])
        ->assertFiles(['SKILL.md', 'references/checklist.md']);

    SkillsServer::skill(ReleaseChecklistSkill::class)->assertOk()->assertName('release-checklist');
    SkillsServer::skill(new ReleaseChecklistSkill)->assertOk();

    SkillsServer::skillFile('skill://release-checklist/SKILL.md', 'references/checklist.md')
        ->assertOk()
        ->assertSee('Review the changelog and verify the release version.');

    SkillsServer::skillFile(ReleaseChecklistSkill::class)->assertSee('# Release checklist');
});

it('accepts skill class names in the testing helpers', function (): void {
    $server = new class(new FakeTransporter) extends ExampleServer
    {
        protected array $skills = [HouseStyleSkill::class, ReleaseChecklistSkill::class];
    };

    $server::skill(HouseStyleSkill::class)->assertOk()->assertName('house-style')->assertFrontmatter(['license' => 'MIT']);
    $server::skill(ReleaseChecklistSkill::class)->assertOk()->assertFiles(['SKILL.md', 'references/checklist.md']);
    $server::skillFile(HouseStyleSkill::class)->assertSee('Be brief.');

    config(['mcp.testing.house-style' => false]);

    $server::skill(HouseStyleSkill::class)->assertNotRegistered();
});

it('reports unregistered skills through the testing helpers', function (): void {
    SkillsServer::skill('skill://other/release-checklist/SKILL.md')
        ->assertNotRegistered()
        ->assertHasErrors(['not found']);

    expect(fn (): TestResponse => SkillsServer::skillFile('skill://other/release-checklist/SKILL.md'))
        ->toThrow(InvalidArgumentException::class, 'not registered');

    SkillsServer::skillFile(HouseStyleSkill::class)
        ->assertHasErrors();

    expect(fn (): TestSkillResponse => SkillsServer::skill('skills/release-checklist'))
        ->toThrow(InvalidArgumentException::class, 'must be a skill URI or a Skill class');
});
