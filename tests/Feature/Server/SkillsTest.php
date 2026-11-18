<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Skill;
use Tests\Fixtures\ArrayTransport;
use Tests\Fixtures\ExampleServer;
use Tests\Fixtures\HouseStyleSkill;
use Tests\Fixtures\LastLogLineResource;
use Tests\Fixtures\ReleaseChecklistSkill;
use Tests\Fixtures\SkillsServer;
use Tests\Fixtures\TeamReleaseChecklistSkill;

function skillRequest(Server $server, ArrayTransport $transport, string $method, array $params = []): array
{
    $server->handle(json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => $method,
        'params' => ['_meta' => protocolMeta(), ...$params],
    ]));

    return json_decode(end($transport->sent), true);
}

function temporarySkill(string $directory, string $frontmatter = "name: demo\ndescription: A demo skill."): Skill
{
    (new Filesystem)->ensureDirectoryExists($directory);
    file_put_contents($directory.'/SKILL.md', "---\n{$frontmatter}\n---\n\n# Demo\n");

    return new class($directory) extends Skill
    {
        public function __construct(protected string $directory)
        {
            //
        }

        public function path(): string
        {
            return $this->directory;
        }
    };
}

beforeEach(function (): void {
    $this->transport = new ArrayTransport;
    $this->server = new SkillsServer($this->transport);
    $this->server->start();

    $this->directory = __DIR__.'/../../Fixtures/Skills/release-checklist';
    $this->temporary = sys_get_temp_dir().'/mcp-skill-'.bin2hex(random_bytes(6));
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->temporary);
});

it('declares the skills extension and the resources capability', function (): void {
    $capabilities = skillRequest($this->server, $this->transport, 'server/discover')['result']['capabilities'];

    expect($capabilities)->toHaveKey('resources')
        ->and($capabilities['extensions'])->toHaveKey('io.modelcontextprotocol/skills');

    $server = new ExampleServer($this->transport);
    $server->start();

    expect(skillRequest($server, $this->transport, 'server/discover')['result']['capabilities'])->not->toHaveKey('extensions');
});

it('lists skills with their frontmatter and a manifest of every file', function (): void {
    $result = skillRequest($this->server, $this->transport, 'skills/list')['result'];

    expect($result)->toHaveKeys(['resultType', 'ttlMs', 'cacheScope'])
        ->and($result['skills'])->toBe([[
            'uri' => 'skill://release-checklist/SKILL.md',
            'frontmatter' => [
                'name' => 'release-checklist',
                'description' => 'Prepare a release using the bundled checklist.',
                'license' => 'MIT',
                'compatibility' => 'Requires a project with a changelog.',
                'metadata' => ['version' => '1.0'],
                'allowed-tools' => 'Read',
            ],
            'resources' => [
                [
                    'uri' => 'skill://release-checklist/SKILL.md',
                    'digest' => 'sha256:'.hash_file('sha256', $this->directory.'/SKILL.md'),
                    'size' => filesize($this->directory.'/SKILL.md'),
                ],
                [
                    'uri' => 'skill://release-checklist/references/checklist.md',
                    'digest' => 'sha256:'.hash_file('sha256', $this->directory.'/references/checklist.md'),
                    'size' => filesize($this->directory.'/references/checklist.md'),
                ],
            ],
        ]]);
});

it('gets a skill by the uri of its SKILL.md', function (): void {
    $list = skillRequest($this->server, $this->transport, 'skills/list')['result']['skills'][0];
    $get = skillRequest($this->server, $this->transport, 'skills/get', ['uri' => 'skill://release-checklist/SKILL.md']);

    expect($get['result']['skill'])->toBe($list);

    expect(skillRequest($this->server, $this->transport, 'skills/get', ['uri' => 'skill://missing/SKILL.md'])['error']['code'])->toBe(-32602)
        ->and(skillRequest($this->server, $this->transport, 'skills/get', ['uri' => ['skill://release-checklist/SKILL.md']])['error']['code'])->toBe(-32602);
});

it('lists only SKILL.md as a resource but reads every skill file', function (): void {
    $resources = array_values(array_filter(
        skillRequest($this->server, $this->transport, 'resources/list')['result']['resources'],
        fn (array $resource): bool => str_starts_with($resource['uri'], 'skill://'),
    ));

    expect($resources)->toHaveCount(1)
        ->and($resources[0])->toMatchArray([
            'uri' => 'skill://release-checklist/SKILL.md',
            'name' => 'release-checklist',
            'title' => 'release-checklist',
            'description' => 'Prepare a release using the bundled checklist.',
            'mimeType' => 'text/markdown',
        ]);

    $read = skillRequest($this->server, $this->transport, 'resources/read', ['uri' => 'skill://release-checklist/references/checklist.md']);

    expect($read['result']['contents'][0]['text'])->toBe(file_get_contents($this->directory.'/references/checklist.md'));
});

it('hides skills that should not register', function (): void {
    config(['mcp.testing.house-style' => false]);

    $server = new class($this->transport) extends ExampleServer
    {
        protected array $skills = [HouseStyleSkill::class, ReleaseChecklistSkill::class];
    };
    $server->start();

    expect(array_column(skillRequest($server, $this->transport, 'skills/list')['result']['skills'], 'uri'))
        ->toBe(['skill://release-checklist/SKILL.md'])
        ->and(skillRequest($server, $this->transport, 'skills/get', ['uri' => 'skill://house-style/SKILL.md'])['error']['code'])
        ->toBe(-32602)
        ->and(skillRequest($server, $this->transport, 'resources/read', ['uri' => 'skill://house-style/SKILL.md'])['error']['code'])
        ->toBe(-32602)
        ->and(array_column(skillRequest($server, $this->transport, 'resources/list')['result']['resources'], 'uri'))
        ->not->toContain('skill://house-style/SKILL.md');
});

it('registers a skill folder by its path', function (): void {
    $server = new class($this->transport) extends ExampleServer
    {
        protected array $skills = [__DIR__.'/../../Fixtures/Skills/house-style', ReleaseChecklistSkill::class];
    };
    $server->start();

    expect(array_column(skillRequest($server, $this->transport, 'skills/list')['result']['skills'], 'uri'))->toBe([
        'skill://house-style/SKILL.md',
        'skill://release-checklist/SKILL.md',
    ])->and(skillRequest($server, $this->transport, 'resources/read', ['uri' => 'skill://house-style/SKILL.md'])['result']['contents'][0]['text'])
        ->toContain('Be brief.');
});

it('uses the uri attribute as the skill uri', function (): void {
    $skill = new TeamReleaseChecklistSkill;

    expect($skill->uri())->toBe('skill://team/release-checklist/SKILL.md')
        ->and(array_column($skill->toArray()['resources'], 'uri'))->toBe([
            'skill://team/release-checklist/SKILL.md',
            'skill://team/release-checklist/references/checklist.md',
        ]);
});

it('resolves relative paths from the resources path and skips dotfiles', function (): void {
    $this->beforeApplicationDestroyed(lockSharedFilesystem());

    $files = new Filesystem;
    $files->copyDirectory($this->directory, resource_path('skills/release-checklist'));
    $files->put(resource_path('skills/release-checklist/.env'), 'SECRET=1');

    try {
        $skill = new class extends Skill
        {
            public function path(): string
            {
                return 'skills/release-checklist';
            }
        };

        expect($skill->directory())->toBe(resource_path('skills/release-checklist'))
            ->and($skill->files())->toBe(['SKILL.md', 'references/checklist.md']);
    } finally {
        $files->deleteDirectory(resource_path('skills'));
    }
});

it('requires a valid name and a description in the frontmatter', function (string $frontmatter, string $message): void {
    $skill = temporarySkill($this->temporary.'/demo', $frontmatter);

    expect(fn (): array => $skill->frontmatter())->toThrow(InvalidArgumentException::class, $message);
})->with([
    'missing description' => ['name: demo', 'must have a SKILL.md with a valid [name] and a [description]'],
    'empty description' => ["name: demo\ndescription: ''", 'must have a SKILL.md with a valid [name] and a [description]'],
    'long description' => ["name: demo\ndescription: ".str_repeat('a', 1025), 'must have a SKILL.md with a valid [name] and a [description]'],
    'uppercase name' => ["name: Demo\ndescription: A demo skill.", 'must have a SKILL.md with a valid [name] and a [description]'],
    'path in name' => ["name: ../demo\ndescription: A demo skill.", 'must have a SKILL.md with a valid [name] and a [description]'],
    'long name' => ['name: '.str_repeat('a', 65)."\ndescription: A demo skill.", 'must have a SKILL.md with a valid [name] and a [description]'],
    'invalid yaml' => ["name: [demo\ndescription: A demo skill.", 'must have a SKILL.md with a valid [name] and a [description]'],
    'php object tag' => ["name: demo\ndescription: A demo skill.\nobject: !php/object 'O:8:\"stdClass\":0:{}'", 'must have a SKILL.md with a valid [name] and a [description]'],
    'name not matching the directory' => ["name: other\ndescription: A demo skill.", 'must match its directory'],
]);

it('requires a SKILL.md with a frontmatter block', function (): void {
    $skill = temporarySkill($this->temporary.'/demo');
    file_put_contents($this->temporary.'/demo/SKILL.md', "# Demo\n");

    $skill->frontmatter();
})->throws(InvalidArgumentException::class, 'must have a SKILL.md with a valid [name] and a [description]');

it('keeps frontmatter dates as written', function (): void {
    $skill = temporarySkill($this->temporary.'/demo', "name: demo\ndescription: A demo skill.\nupdated: 2024-01-01\nreleased: 2024-01-01T10:30:00+02:00");

    expect($skill->frontmatter())->toMatchArray([
        'updated' => '2024-01-01',
        'released' => '2024-01-01T10:30:00+02:00',
    ]);
});

it('requires the uri attribute to end with the skill name and SKILL.md', function (): void {
    temporarySkill($this->temporary.'/demo');

    $missingFile = new #[Uri('skill://team/demo')] class($this->temporary.'/demo') extends Skill
    {
        public function __construct(protected string $directory)
        {
            //
        }

        public function path(): string
        {
            return $this->directory;
        }
    };

    $otherName = new #[Uri('skill://team/other/SKILL.md')] class($this->temporary.'/demo') extends Skill
    {
        public function __construct(protected string $directory)
        {
            //
        }

        public function path(): string
        {
            return $this->directory;
        }
    };

    expect(fn (): string => $missingFile->uri())->toThrow(InvalidArgumentException::class, 'must end with [/demo/SKILL.md]')
        ->and(fn (): string => $otherName->uri())->toThrow(InvalidArgumentException::class, 'must end with [/demo/SKILL.md]');
});

it('reports and skips invalid skills without affecting other resources', function (): void {
    Exceptions::fake();

    temporarySkill($this->temporary.'/broken', "name: Broken\ndescription: A broken skill.");
    temporarySkill($this->temporary.'/first/demo');
    temporarySkill($this->temporary.'/second/demo');

    $server = new class($this->transport) extends ExampleServer
    {
        public array $skills = [];
    };
    $server->skills = [
        $this->temporary.'/broken',
        $this->temporary.'/missing',
        $this->temporary.'/first/demo',
        $this->temporary.'/second/demo',
        ReleaseChecklistSkill::class,
    ];
    $server->start();

    $resource = new LastLogLineResource;

    expect(array_column(skillRequest($server, $this->transport, 'skills/list')['result']['skills'], 'uri'))
        ->toBe(['skill://demo/SKILL.md', 'skill://release-checklist/SKILL.md'])
        ->and(array_column(skillRequest($server, $this->transport, 'resources/list')['result']['resources'], 'uri'))
        ->toContain($resource->uri(), 'skill://demo/SKILL.md', 'skill://release-checklist/SKILL.md')
        ->and(skillRequest($server, $this->transport, 'resources/read', ['uri' => $resource->uri()]))
        ->toHaveKey('result')
        ->and(skillRequest($server, $this->transport, 'resources/read', ['uri' => 'skill://demo/SKILL.md'])['result']['contents'][0]['text'])
        ->toContain('# Demo');

    Exceptions::assertReported(fn (InvalidArgumentException $e): bool => str_contains($e->getMessage(), 'must have a SKILL.md'));
    Exceptions::assertReported(fn (InvalidArgumentException $e): bool => str_contains($e->getMessage(), 'does not exist'));
    Exceptions::assertReported(fn (InvalidArgumentException $e): bool => str_contains($e->getMessage(), 'is already registered'));
});

it('validates skills/list pagination', function (): void {
    $server = new class($this->transport) extends ExampleServer
    {
        public array $skills = [HouseStyleSkill::class, ReleaseChecklistSkill::class];
    };
    $server->start();

    $first = skillRequest($server, $this->transport, 'skills/list', ['per_page' => 1])['result'];
    $second = skillRequest($server, $this->transport, 'skills/list', ['per_page' => 1, 'cursor' => $first['nextCursor']])['result'];

    expect(array_column($first['skills'], 'uri'))->toBe(['skill://house-style/SKILL.md'])
        ->and(array_column($second['skills'], 'uri'))->toBe(['skill://release-checklist/SKILL.md'])
        ->and($second)->not->toHaveKey('nextCursor')
        ->and(skillRequest($server, $this->transport, 'skills/list', ['per_page' => 0])['error']['code'])->toBe(-32602)
        ->and(skillRequest($server, $this->transport, 'skills/list', ['per_page' => '1'])['error']['code'])->toBe(-32602)
        ->and(skillRequest($server, $this->transport, 'skills/list', ['cursor' => 1])['error']['code'])->toBe(-32602);
});

it('reads skill files by their encoded or unencoded uri', function (): void {
    temporarySkill($this->temporary.'/demo');
    (new Filesystem)->ensureDirectoryExists($this->temporary.'/demo/ref');
    file_put_contents($this->temporary.'/demo/ref/a (1).md', 'Notes');
    file_put_contents($this->temporary.'/demo/logo.png', $png = "\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\x01\0\0\0\x01\x08\x06\0\0\0\x1f\x15\xc4\x89");

    $server = new class($this->transport) extends ExampleServer
    {
        public array $skills = [];
    };
    $server->skills = [$this->temporary.'/demo'];
    $server->start();

    $manifest = array_column(skillRequest($server, $this->transport, 'skills/get', ['uri' => 'skill://demo/SKILL.md'])['result']['skill']['resources'], 'uri');
    $binary = skillRequest($server, $this->transport, 'resources/read', ['uri' => 'skill://demo/logo.png'])['result']['contents'][0];

    expect($manifest)->toBe(['skill://demo/SKILL.md', 'skill://demo/logo.png', 'skill://demo/ref/a%20%281%29.md'])
        ->and(skillRequest($server, $this->transport, 'resources/read', ['uri' => 'skill://demo/ref/a%20%281%29.md'])['result']['contents'][0]['text'])->toBe('Notes')
        ->and(skillRequest($server, $this->transport, 'resources/read', ['uri' => 'skill://demo/ref/a%20(1).md'])['result']['contents'][0]['text'])->toBe('Notes')
        ->and($binary['blob'])->toBe(base64_encode($png))
        ->and($binary['mimeType'])->toBe('image/png');
});

it('never serves files outside the skill directory', function (): void {
    $skill = temporarySkill($this->temporary.'/demo');
    file_put_contents($this->temporary.'/secret.txt', 'secret');
    symlink($this->temporary.'/secret.txt', $this->temporary.'/demo/linked.txt');
    symlink($this->temporary, $this->temporary.'/demo/parent');

    $server = new class($this->transport) extends ExampleServer
    {
        public array $skills = [];
    };
    $server->skills = [$skill];
    $server->start();

    expect($skill->files())->toBe(['SKILL.md'])
        ->and(fn (): string => $skill->read('../secret.txt'))->toThrow(InvalidArgumentException::class, 'is not readable')
        ->and(fn (): string => $skill->read('linked.txt'))->toThrow(InvalidArgumentException::class, 'is not readable');

    foreach (['skill://demo/linked.txt', 'skill://demo/../secret.txt', 'skill://demo/parent/secret.txt'] as $uri) {
        expect(skillRequest($server, $this->transport, 'resources/read', ['uri' => $uri])['error']['code'])->toBe(-32602);
    }
})->skipOnWindows();

it('refuses skills over the file count limit', function (): void {
    $skill = temporarySkill($this->temporary.'/demo');

    foreach (range(1, Skill::MAX_FILES) as $index) {
        touch($this->temporary."/demo/{$index}.txt");
    }

    $skill->files();
})->throws(InvalidArgumentException::class, 'must not contain more than 512 files or 16 MiB');

it('refuses skills over the size limit', function (): void {
    $skill = temporarySkill($this->temporary.'/demo');

    $handle = fopen($this->temporary.'/demo/large.bin', 'w');
    ftruncate($handle, Skill::MAX_SIZE + 1);
    fclose($handle);

    expect(fn (): array => $skill->files())->toThrow(InvalidArgumentException::class, 'must not contain more than 512 files or 16 MiB')
        ->and(fn (): string => $skill->read('large.bin'))->toThrow(InvalidArgumentException::class, 'is not readable');
});

it('skips dot directories', function (): void {
    $skill = temporarySkill($this->temporary.'/demo');
    (new Filesystem)->ensureDirectoryExists($this->temporary.'/demo/.git');
    file_put_contents($this->temporary.'/demo/.git/config', 'secret');

    expect($skill->files())->toBe(['SKILL.md']);
});
