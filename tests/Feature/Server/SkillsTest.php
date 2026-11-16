<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Mcp\Enums\CacheScope;
use Laravel\Mcp\Enums\Extension;
use Laravel\Mcp\Exceptions\InvalidSkillException;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Cacheable;
use Laravel\Mcp\Server\Skill;
use Laravel\Mcp\Server\Skills\SkillCatalog;
use Laravel\Mcp\Server\Skills\SkillDirectory;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tests\Fixtures\ArrayTransport;
use Tests\Fixtures\EmptySkillListing;
use Tests\Fixtures\ExampleServer;
use Tests\Fixtures\HouseStyleSkill;
use Tests\Fixtures\ReleaseChecklistSkill;
use Tests\Fixtures\SkillsServer;

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

it('discovers every skill in a parent directory', function (): void {
    $files = new Filesystem;
    $files->copyDirectory(fixtureSkillPath(), $this->temporary.'/release-checklist');
    $files->ensureDirectoryExists($this->temporary.'/hotfix');
    file_put_contents($this->temporary.'/hotfix/SKILL.md', "---\nname: hotfix\ndescription: Ship a hotfix.\n---\n");
    $files->ensureDirectoryExists($this->temporary.'/not-a-skill');
    $files->ensureDirectoryExists($this->temporary.'/.archived');
    file_put_contents($this->temporary.'/.archived/SKILL.md', "---\nname: archived\ndescription: Old.\n---\n");

    $this->server->skills = [$this->temporary];
    $this->server->start();

    expect(array_column(skillResponse($this->server, $this->transport, 'skills/list')['result']['skills'], 'uri'))->toBe([
        'skill://hotfix/SKILL.md',
        'skill://release-checklist/SKILL.md',
    ]);
});

it('registers skills from the skills method', function (string $uri): void {
    $server = new class($this->transport) extends Server
    {
        public string $skillUri = '';

        protected function skills(): iterable
        {
            return [Skill::fromDirectory(fixtureSkillPath(), $this->skillUri ?: null)];
        }
    };
    $server->skillUri = $uri;
    $server->start();

    expect(skillResponse($server, $this->transport, 'server/discover')['result']['capabilities']['extensions'])
        ->toHaveKey('io.modelcontextprotocol/skills');

    $entry = skillResponse($server, $this->transport, 'skills/list')['result']['skills'][0];
    expect($entry['uri'])->toBe($uri ?: 'skill://release-checklist/SKILL.md')
        ->and(skillResponse($server, $this->transport, 'skills/get', ['uri' => $entry['uri']])['result']['skill'])->toBe($entry);

    foreach ($entry['resources'] as $resource) {
        $content = skillResponse($server, $this->transport, 'resources/read', ['uri' => $resource['uri']])['result']['contents'][0];
        expect(strlen($content['text']))->toBe($resource['size'])
            ->and('sha256:'.hash('sha256', $content['text']))->toBe($resource['digest']);
    }
})->with(['', 'skill://team/release-checklist/SKILL.md']);

it('serves inline skills end to end', function (): void {
    $this->server->skills = [new Skill('house-style', 'Use when you write copy for a customer.', "# House Style\n\nBe brief.\n")];
    $this->server->start();

    $entry = skillResponse($this->server, $this->transport, 'skills/get', ['uri' => 'skill://house-style/SKILL.md'])['result']['skill'];
    $content = skillResponse($this->server, $this->transport, 'resources/read', ['uri' => 'skill://house-style/SKILL.md'])['result']['contents'][0];

    expect($entry['frontmatter'])->toBe(['name' => 'house-style', 'description' => 'Use when you write copy for a customer.'])
        ->and($content['mimeType'])->toBe('text/markdown')
        ->and('sha256:'.hash('sha256', $content['text']))->toBe($entry['resources'][0]['digest'])
        ->and(strlen($content['text']))->toBe($entry['resources'][0]['size']);
});

it('serves skill classes registered by class name', function (): void {
    $this->server->skills = [HouseStyleSkill::class, ReleaseChecklistSkill::class];
    $this->server->start();

    $skills = skillResponse($this->server, $this->transport, 'skills/list')['result']['skills'];
    $content = skillResponse($this->server, $this->transport, 'resources/read', ['uri' => 'skill://house-style/SKILL.md'])['result']['contents'][0];

    expect(array_column($skills, 'uri'))->toBe(['skill://house-style/SKILL.md', 'skill://team/release-checklist/SKILL.md'])
        ->and($skills[0]['frontmatter'])->toBe(['license' => 'MIT', 'name' => 'house-style', 'description' => 'Use when you write copy for a customer.'])
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

it('accepts skill classes that pass their definition to the parent constructor', function (): void {
    $this->server->skills = [new class extends Skill
    {
        public function __construct()
        {
            parent::__construct('house-style', 'Use when writing copy.', '# House Style');
        }
    }];
    $this->server->start();

    expect(skillResponse($this->server, $this->transport, 'skills/get', ['uri' => 'skill://house-style/SKILL.md'])['result']['skill']['frontmatter'])
        ->toBe(['name' => 'house-style', 'description' => 'Use when writing copy.']);
});

it('resolves relative skill directories from the resources path', function (): void {
    $files = new Filesystem;
    $files->copyDirectory(fixtureSkillPath(), resource_path('skills/release-checklist'));

    try {
        $this->server->skills = ['skills', Skill::fromDirectory('skills/release-checklist', 'skill://copy/release-checklist/SKILL.md')];
        $this->server->start();

        expect(array_column(skillResponse($this->server, $this->transport, 'skills/list')['result']['skills'], 'uri'))
            ->toBe(['skill://release-checklist/SKILL.md', 'skill://copy/release-checklist/SKILL.md'])
            ->and(Skill::fromDirectory('skills/release-checklist')->directory()->path)->toBe(resource_path('skills/release-checklist'))
            ->and(Skill::fromDirectory('/absolute/path')->directory()->path)->toBe('/absolute/path');
    } finally {
        $files->deleteDirectory(resource_path('skills'));
    }
});

it('evaluates closure sources lazily with dependency injection', function (): void {
    $calls = 0;
    $this->server->skills = [function (Request $request) use (&$calls): array {
        $calls++;

        return [fixtureSkillPath(), new Skill('house-style', 'Use when writing copy.')];
    }];
    $this->server->start();

    skillResponse($this->server, $this->transport, 'tools/list');
    expect($calls)->toBe(0);

    expect(array_column(skillResponse($this->server, $this->transport, 'skills/list')['result']['skills'], 'uri'))
        ->toBe(['skill://release-checklist/SKILL.md', 'skill://house-style/SKILL.md'])
        ->and($calls)->toBe(1);
});

it('applies conditional registration through closures to listing getting and file reads', function (mixed $result): void {
    $this->server->skills = [fn (): mixed => $result];
    $this->server->start();

    expect(skillResponse($this->server, $this->transport, 'skills/list')['result']['skills'])->toBe([])
        ->and(skillResponse($this->server, $this->transport, 'skills/get', ['uri' => 'skill://release-checklist/SKILL.md'])['error']['code'])->toBe(-32602)
        ->and(skillResponse($this->server, $this->transport, 'resources/read', ['uri' => 'skill://release-checklist/references/checklist.md'])['error']['code'])->toBe(-32602);
})->with([[[]], [null]]);

it('accepts a single skill returned from a closure', function (): void {
    $this->server->skills = [fn (): Skill => new Skill('house-style', 'Use when writing copy.')];
    $this->server->start();

    expect(skillResponse($this->server, $this->transport, 'skills/list')['result']['skills'][0]['uri'])->toBe('skill://house-style/SKILL.md');
});

it('rejects unsupported skill sources', function (): void {
    $this->server->skills = [123];
    expect(fn () => $this->server->createContext()->skills())->toThrow(InvalidArgumentException::class, 'Skill sources must be');
});

it('can declare an empty skill catalog explicitly', function (): void {
    $server = new class($this->transport) extends Server
    {
        protected array $extensions = [Extension::Skills];
    };
    $server->start();
    expect(skillResponse($server, $this->transport, 'skills/list')['result']['skills'])->toBe([])
        ->and(skillResponse($server, $this->transport, 'skills/get', ['uri' => 'skill://unknown/SKILL.md'])['error']['code'])->toBe(-32602);
});

it('declares the skills extension when the skills method is overridden without calling it', function (): void {
    $server = new class($this->transport) extends Server
    {
        public int $calls = 0;

        protected function skills(): iterable
        {
            $this->calls++;

            return [];
        }
    };
    $server->start();

    $capabilities = skillResponse($server, $this->transport, 'server/discover')['result']['capabilities'];

    expect($capabilities['extensions'])->toHaveKey('io.modelcontextprotocol/skills')
        ->and($capabilities)->toHaveKey('resources')
        ->and($server->calls)->toBe(0)
        ->and(skillResponse($server, $this->transport, 'skills/list')['result']['skills'])->toBe([])
        ->and($server->calls)->toBe(1);
});

it('declares the skills extension once when it is also declared explicitly', function (): void {
    $server = new class($this->transport) extends Server
    {
        protected array $extensions = [Extension::Skills];

        protected array $skills = [__DIR__.'/../../Fixtures/Skills'];
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
        Skill::fromDirectory(fixtureSkillPath(), 'skill://first/release-checklist/SKILL.md'),
        Skill::fromDirectory(fixtureSkillPath(), 'skill://second/release-checklist/SKILL.md'),
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
    $this->server->skills = [fixtureSkillPath(), Skill::fromDirectory(fixtureSkillPath())];
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
    $this->server->skills = [$path];
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
    'empty path' => [''],
    'missing directory' => ['/nonexistent/skill'],
    'filesystem root' => [fn (): Skill => Skill::fromDirectory('/')],
    'invalid inline skill' => [fn (): Skill => new Skill('Bad Name', 'Description')],
]);

it('reports each invalid skill once with its path', function (): void {
    Exceptions::fake();
    $this->server->skills[] = '/nonexistent/skill';
    $this->server->start();

    skillResponse($this->server, $this->transport, 'skills/list');
    skillResponse($this->server, $this->transport, 'resources/list');

    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(fn (InvalidSkillException $exception): bool => str_contains($exception->getMessage(), 'The skill [/nonexistent/skill] is invalid.'));
});

it('reports conflicting skill configuration as an internal error on resource reads', function (): void {
    config(['app.debug' => false]);
    $this->server->skills = [fixtureSkillPath(), fixtureSkillPath()];
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

    SkillsServer::skill(fixtureSkillPath())->assertOk()->assertName('release-checklist');
    SkillsServer::skill(Skill::fromDirectory(fixtureSkillPath()))->assertOk();

    SkillsServer::skillFile('skill://release-checklist/SKILL.md', 'references/checklist.md')
        ->assertOk()
        ->assertSee('Review the changelog and verify the release version.');

    SkillsServer::skillFile(fixtureSkillPath())->assertSee('# Release checklist');
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

    SkillsServer::skillFile(new Skill('house-style', 'Use when writing copy.'))
        ->assertHasErrors();
});
