<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Laravel\Mcp\Enums\CacheScope;
use Laravel\Mcp\Enums\Extension;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Cacheable;
use Laravel\Mcp\Server\Methods\ListSkills;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Tests\Fixtures\ArrayTransport;
use Tests\Fixtures\ExampleServer;
use Tests\Fixtures\SkillsServer;
use Tests\Fixtures\TestSkill;

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

beforeEach(function (): void {
    $this->transport = new ArrayTransport;
    $this->server = new SkillsServer($this->transport);
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

it('can declare an empty skill catalog explicitly', function (): void {
    $server = new class($this->transport) extends Server
    {
        protected array $extensions = [Extension::Skills];
    };
    $server->start();
    expect(skillResponse($server, $this->transport, 'skills/list')['result']['skills'])->toBe([])
        ->and(skillResponse($server, $this->transport, 'skills/get', ['uri' => 'skill://unknown/SKILL.md'])['error']['code'])->toBe(-32602);
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
        new TestSkill(uri: 'skill://first/release-checklist/SKILL.md'),
        new TestSkill(uri: 'skill://second/release-checklist/SKILL.md'),
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

    // A client can get an entry before reaching its page in the listing.
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

it('applies conditional registration to listing getting and file reads', function (): void {
    $this->server->skills = [new class extends TestSkill
    {
        public function shouldRegister(): bool
        {
            return false;
        }
    }];
    $this->server->start();
    expect(skillResponse($this->server, $this->transport, 'skills/list')['result']['skills'])->toBe([])
        ->and(skillResponse($this->server, $this->transport, 'skills/get', ['uri' => 'skill://release-checklist/SKILL.md'])['error']['code'])->toBe(-32602)
        ->and(skillResponse($this->server, $this->transport, 'resources/read', ['uri' => 'skill://release-checklist/references/checklist.md'])['error']['code'])->toBe(-32602);
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
    'file:///etc/passwd',
]);

it('keeps resources prompts and tools available alongside skill files', function (): void {
    $this->server->start();
    $resources = skillResponse($this->server, $this->transport, 'resources/list')['result']['resources'];
    expect(array_column($resources, 'uri'))->toContain('file://resources/last-log-line-resource', 'skill://release-checklist/SKILL.md')
        ->and(skillResponse($this->server, $this->transport, 'resources/read', ['uri' => 'file://resources/last-log-line-resource'])['result']['contents'][0]['text'])->toBe('2025-07-02 12:00:00 Error: Something went wrong.')
        ->and(skillResponse($this->server, $this->transport, 'tools/list')['result']['tools'])->toHaveCount(2)
        ->and(skillResponse($this->server, $this->transport, 'prompts/list')['result']['prompts'])->toBe([]);

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
    $this->server->skills = [TestSkill::class, new TestSkill];
    expect(fn () => $this->server->createContext()->skills())->toThrow(InvalidArgumentException::class, 'Duplicate server skill URI');
});

it('rejects registered resources that would shadow skill file bytes', function (): void {
    $this->server->resources[] = $this->makeResource('wrong bytes', overrides: ['uri' => 'skill://release-checklist/SKILL.md']);
    expect(fn () => $this->server->createContext()->resources())->toThrow(InvalidArgumentException::class, 'already registered');
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
    $directory = sys_get_temp_dir().'/mcp-skills-'.bin2hex(random_bytes(8));
    $files = new Filesystem;
    $files->copyDirectory(__DIR__.'/../../Fixtures/Skills/release-checklist', $directory.'/release-checklist');

    try {
        $skill = new TestSkill($directory.'/release-checklist');
        $bytes = file_get_contents(__DIR__.'/../../Fixtures/binary.png');
        file_put_contents($skill->path().'/a file%.png', $bytes);
        $this->server->skills = [$skill];
        $this->server->start();
        $uri = 'skill://release-checklist/a%20file%25.png';
        $result = skillResponse($this->server, $this->transport, 'resources/read', ['uri' => $uri])['result'];
        expect($result['contents'][0]['mimeType'])->toBe('image/png')
            ->and(base64_decode($result['contents'][0]['blob'], true))->toBe($bytes);

        $first = skillResponse($this->server, $this->transport, 'skills/get', ['uri' => $skill->uri()])['result']['skill'];
        $manifest = collect($first['resources'])->firstWhere('uri', $uri);
        expect($manifest['size'])->toBe(strlen($bytes))
            ->and($manifest['digest'])->toBe('sha256:'.hash('sha256', $bytes));
        file_put_contents($skill->path().'/references/checklist.md', "Updated instructions\r\n");
        $second = skillResponse($this->server, $this->transport, 'skills/get', ['uri' => $skill->uri()])['result']['skill'];
        expect($second['resources'])->not->toBe($first['resources']);
    } finally {
        $files->deleteDirectory($directory);
    }
});

class EmptySkillListing extends ListSkills
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        return JsonRpcResponse::result($request->id, ['skills' => []]);
    }
}

it('rejects malformed skill pagination parameters', function (array $params): void {
    $this->server->start();
    expect(skillResponse($this->server, $this->transport, 'skills/list', $params)['error']['code'])->toBe(-32602);
})->with([
    [['cursor' => []]],
    [['cursor' => 12]],
    [['per_page' => 0]],
    [['per_page' => -1]],
    [['per_page' => '2']],
]);

it('reports invalid server skill configuration as an internal error', function (): void {
    config(['app.debug' => false]);
    $this->server->skills = [new TestSkill('/nonexistent/skill')];
    $this->server->start();

    $response = skillResponse($this->server, $this->transport, 'skills/list');
    expect($response['error']['code'])->toBe(-32603)
        ->and($response['error']['message'])->not->toContain('/nonexistent/skill');
});

it('retains mandatory cache fields when unrelated continuation params are supplied', function (string $method): void {
    $this->server->start();
    $result = skillResponse($this->server, $this->transport, $method, [
        'uri' => 'skill://release-checklist/SKILL.md',
        'requestState' => 'unused',
    ])['result'];
    expect($result)->toHaveKeys(['ttlMs', 'cacheScope']);
})->with(['skills/list', 'skills/get']);
