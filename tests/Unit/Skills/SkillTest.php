<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Laravel\Mcp\Exceptions\InvalidSkillException;
use Laravel\Mcp\Server\Methods\GetSkill;
use Laravel\Mcp\Server\Methods\ListSkills;
use Laravel\Mcp\Server\Skill;
use Laravel\Mcp\Server\Skills\SkillDirectory;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Tests\Fixtures\DirectorySkill;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/mcp-skills-'.bin2hex(random_bytes(8));
    $this->files = new Filesystem;
    $this->files->copyDirectory(__DIR__.'/../../Fixtures/Skills/release-checklist', $this->directory.'/release-checklist');

    $this->path = $this->directory.'/release-checklist';
    $this->skill = new DirectorySkill($this->path);
});

afterEach(function (): void {
    $this->files->deleteDirectory($this->directory);
    SkillDirectory::flushDigests();
});

it('describes SKILL.md with the frontmatter and digest of the same bytes', function (): void {
    $original = file_get_contents($this->path.'/SKILL.md');
    $skill = new class($this->path) extends DirectorySkill
    {
        public int $reads = 0;

        public function read(string $file): string
        {
            $contents = parent::read($file);

            if ($file === 'SKILL.md' && ++$this->reads === 2) {
                file_put_contents($this->path().'/SKILL.md', str_replace('Prepare a release', 'Ship a release', $contents));
            }

            return $contents;
        }
    };

    $entry = $skill->toArray();

    expect($entry['frontmatter']['description'])->toBe('Prepare a release using the bundled checklist.')
        ->and($entry['resources'][0]['uri'])->toBe('skill://release-checklist/SKILL.md')
        ->and($entry['resources'][0])->toMatchArray(SkillDirectory::hash($original));
});

it('accepts a SKILL.md that starts with a byte order mark and digests its raw bytes', function (): void {
    $contents = "\u{FEFF}".file_get_contents($this->path.'/SKILL.md');
    file_put_contents($this->path.'/SKILL.md', $contents);

    $entry = $this->skill->toArray();

    expect($entry['frontmatter']['name'])->toBe('release-checklist')
        ->and($entry['resources'][0])->toMatchArray(SkillDirectory::hash($contents));
});

it('preserves all frontmatter fields and JSON object and array shapes', function (): void {
    $entry = $this->skill->toArray();

    expect($entry['uri'])->toBe('skill://release-checklist/SKILL.md')
        ->and($entry['frontmatter']['name'])->toBe('release-checklist')
        ->and($entry['frontmatter']['description'])->toBe('Prepare a release using the bundled checklist.')
        ->and($entry['frontmatter']['license'])->toBe('MIT')
        ->and($entry['frontmatter']['metadata']->version)->toBe('1.0')
        ->and($entry['frontmatter']['allowed-tools'])->toBe('Read')
        ->and($entry['frontmatter']['custom']->enabled)->toBeTrue()
        ->and($entry['frontmatter']['custom']->options)->toBeInstanceOf(stdClass::class)
        ->and($entry['frontmatter']['custom']->steps)->toBe([])
        ->and($this->skill->name())->toBe('release-checklist')
        ->and($this->skill->description())->toBe($entry['frontmatter']['description']);
});

it('builds a complete sorted manifest of nested files from raw bytes and skips dotfiles', function (): void {
    mkdir($this->path.'/nested');
    mkdir($this->path.'/.git');
    file_put_contents($this->path.'/nested/SKILL.md', "---\nname: nested\ndescription: Nested instructions\n---\n");
    file_put_contents($this->path.'/.env', "SECRET=1\n");
    file_put_contents($this->path.'/.git/config', "[core]\n");
    file_put_contents($this->path.'/references/a space #%.bin', "\0\xffbinary\r\n");

    $entry = $this->skill->toArray();

    expect(array_column($entry['resources'], 'uri'))->toBe([
        'skill://release-checklist/SKILL.md',
        'skill://release-checklist/nested/SKILL.md',
        'skill://release-checklist/references/a%20space%20%23%25.bin',
        'skill://release-checklist/references/checklist.md',
    ]);

    foreach ($entry['resources'] as $resource) {
        $contents = file_get_contents($this->path.'/'.rawurldecode(substr($resource['uri'], strlen('skill://release-checklist/'))));
        expect($resource['size'])->toBe(strlen($contents))
            ->and($resource['digest'])->toBe('sha256:'.hash('sha256', $contents));
    }

    expect(fn () => $this->skill->read('.env'))->toThrow(RuntimeException::class);
});

it('recomputes digests after a file changes', function (): void {
    $before = $this->skill->toArray()['resources'][1];
    file_put_contents($this->path.'/references/checklist.md', strrev((string) file_get_contents($this->path.'/references/checklist.md')));

    expect($this->skill->toArray()['resources'][1]['digest'])->not->toBe($before['digest']);
});

it('supports organizational prefixes and other URI schemes', function (string $uri): void {
    $skill = new DirectorySkill($this->path, $uri);
    expect($skill->toArray()['uri'])->toBe($uri)
        ->and($skill->toArray()['resources'][1]['uri'])->toBe(substr($uri, 0, -8).'references/checklist.md');
})->with(['skill://team/releases/release-checklist/SKILL.md', 'docs://team/release-checklist/SKILL.md']);

it('rejects invalid skill URIs with the skill path in the message', function (string $uri): void {
    $skill = new DirectorySkill($this->path, $uri);
    expect(fn (): array => $skill->toArray())->toThrow(InvalidSkillException::class, 'The skill ['.DirectorySkill::class.'] is invalid. The skill URI');
})->with(['skill://wrong/SKILL.md', 'skill://release-checklist', 'skill://release-checklist/SKILL.md?x=1', 'skill://release-checklist/SKILL.md#part', 'skill:///release-checklist/SKILL.md', 'skill://team/../release-checklist/SKILL.md', 'skill://%2e%2e/release-checklist/SKILL.md', 'skill://bad%2Fprefix/release-checklist/SKILL.md', 'skill://bad%5Cprefix/release-checklist/SKILL.md', 'skill://bad%00prefix/release-checklist/SKILL.md', 'skill://bad%ZZprefix/release-checklist/SKILL.md']);

it('rejects invalid frontmatter', function (string $contents, string $message): void {
    file_put_contents($this->path.'/SKILL.md', $contents);
    expect(fn () => $this->skill->toArray())->toThrow(InvalidSkillException::class, $message);
})->with([
    'missing frontmatter' => ['No frontmatter', 'begin with YAML frontmatter'],
    'missing closing delimiter' => ["---\nname: release-checklist\ndescription: Release\n", 'begin with YAML frontmatter'],
    'missing name' => ["---\ndescription: Release\n---\n", 'must contain a [name]'],
    'numeric name' => ["---\nname: 123\ndescription: Release\n---\n", 'must contain a [name]'],
    'mismatched directory' => ["---\nname: different\ndescription: Release\n---\n", 'must match its directory name [release-checklist]'],
    'uppercase name' => ["---\nname: Release-checklist\ndescription: Release\n---\n", 'lowercase letters'],
    'double hyphen' => ["---\nname: release--checklist\ndescription: Release\n---\n", 'single hyphens'],
    'long name' => ["---\nname: ".str_repeat('x', 65)."\ndescription: Release\n---\n", 'at most 64'],
    'empty description' => ["---\nname: release-checklist\ndescription: ' '\n---\n", 'between 1 and 1024'],
    'missing description' => ["---\nname: release-checklist\n---\n", 'a [description]'],
    'long description' => ["---\nname: release-checklist\ndescription: ".str_repeat('x', 1025)."\n---\n", 'between 1 and 1024'],
    'invalid utf8' => ["---\nname: release-checklist\ndescription: Release\n---\n\xff", 'UTF-8'],
    'nonstring license' => ["---\nname: release-checklist\ndescription: Release\nlicense: true\n---\n", '[license]'],
    'nonstring allowed-tools' => ["---\nname: release-checklist\ndescription: Release\nallowed-tools: [Read]\n---\n", '[allowed-tools]'],
    'empty compatibility' => ["---\nname: release-checklist\ndescription: Release\ncompatibility: ''\n---\n", '[compatibility]'],
    'long compatibility' => ["---\nname: release-checklist\ndescription: Release\ncompatibility: ".str_repeat('x', 501)."\n---\n", '[compatibility]'],
    'metadata sequence' => ["---\nname: release-checklist\ndescription: Release\nmetadata: []\n---\n", '[metadata]'],
    'metadata value' => ["---\nname: release-checklist\ndescription: Release\nmetadata: { version: 1 }\n---\n", '[metadata]'],
    'not a mapping' => ["---\n- name\n- description\n---\n", 'YAML mapping'],
    'malformed yaml' => ["---\nname: release-checklist\ndescription: Release\nbroken: [\n---\n", 'not valid YAML'],
    'php object tag' => ["---\nname: release-checklist\ndescription: Release\nobject: !php/object 'O:8:\"stdClass\":0:{}'\n---\n", 'not valid YAML'],
    'infinite number' => ["---\nname: release-checklist\ndescription: Release\ncustom: .inf\n---\n", 'Infinite'],
    'unquoted date' => ["---\nname: release-checklist\ndescription: Release\npublished-at: 2026-10-05\n---\n", 'Quote dates and timestamps'],
    'nested timestamp' => ["---\nname: release-checklist\ndescription: Release\ncustom: { published-at: 2026-10-05T12:30:00Z }\n---\n", 'Quote dates and timestamps'],
    'date in list' => ["---\nname: release-checklist\ndescription: Release\ncustom: [2026-10-05]\n---\n", 'Quote dates and timestamps'],
]);

it('uses a YAML parser for multiline strings and preserves CRLF bytes', function (): void {
    $contents = "---\r\nname: release-checklist\r\ndescription: >-\r\n  Prepare a release\r\n  with care.\r\n---\r\n# Steps\r\n";
    file_put_contents($this->path.'/SKILL.md', $contents);
    $entry = $this->skill->toArray();
    expect($entry['frontmatter']['description'])->toBe('Prepare a release with care.')
        ->and($entry['resources'][0]['digest'])->toBe('sha256:'.hash('sha256', $contents))
        ->and($this->skill->read('SKILL.md'))->toBe($contents);
});

it('preserves quoted dates and timestamps in unknown frontmatter fields', function (): void {
    file_put_contents($this->path.'/SKILL.md', "---\nname: release-checklist\ndescription: Release\npublished-at: '2026-10-05'\ncustom: { timestamp: '2026-10-05T12:30:00Z' }\n---\n");
    $entry = $this->skill->toArray();
    expect($entry['frontmatter']['published-at'])->toBe('2026-10-05')
        ->and($entry['frontmatter']['custom']->timestamp)->toBe('2026-10-05T12:30:00Z');
});

it('requires an existing directory and SKILL.md', function (): void {
    expect(fn (): array => new DirectorySkill($this->directory.'/missing')->toArray())->toThrow(InvalidSkillException::class, 'must exist');
    expect(fn (): array => new DirectorySkill('')->toArray())->toThrow(InvalidSkillException::class, 'must not be empty');
    unlink($this->path.'/SKILL.md');
    expect(fn () => $this->skill->toArray())->toThrow(InvalidSkillException::class, 'SKILL.md file at its root');
});

it('rejects traversal paths independently of resource resolution', function (string $path): void {
    expect(fn () => $this->skill->read($path))->toThrow(RuntimeException::class);
})->with(['../secret', '/etc/passwd', 'references/../../secret', 'references/../SKILL.md', 'references\\file', "references/\0file", '', 'references//checklist.md', '.hidden', 'references/missing.md']);

it('rejects symbolic links including links within the skill and broken links', function (string $target): void {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('Creating symbolic links requires elevated privileges on Windows.');
    }

    symlink(match ($target) {
        'inside' => $this->path.'/SKILL.md',
        'outside' => $this->directory,
        default => $this->directory.'/missing',
    }, $this->path.'/link');

    expect(fn () => $this->skill->toArray())->toThrow(InvalidSkillException::class, 'symbolic link')
        ->and(fn () => $this->skill->read('link'))->toThrow(RuntimeException::class);
})->with(['inside', 'outside', 'broken']);

it('rejects files during discovery that cannot be read later', function (string $case): void {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('Backslashes and permission bits are not portable to Windows.');
    }

    $path = $this->path.'/references/'.($case === 'backslash' ? 'a\\b.md' : 'locked.md');
    file_put_contents($path, 'contents');

    if ($case === 'unreadable') {
        chmod($path, 0000);

        if (is_readable($path)) {
            $this->markTestSkipped('Permission bits are ignored for this user.');
        }
    }

    expect(fn (): array => $this->skill->resources())->toThrow(InvalidSkillException::class, 'readable');
})->with(['backslash', 'unreadable']);

it('rejects a symbolic link used as the root', function (): void {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('Creating symbolic links requires elevated privileges on Windows.');
    }

    symlink($this->path, $this->directory.'/linked');
    expect(fn (): array => new DirectorySkill($this->directory.'/linked')->toArray())->toThrow(InvalidSkillException::class, 'symbolic link');
});

it('revalidates files and ancestor directories after resource discovery', function (bool $directory): void {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('Creating symbolic links requires elevated privileges on Windows.');
    }

    $resource = $this->skill->resources()[1];
    $path = $this->path.($directory ? '/references' : '/references/checklist.md');
    rename($path, $path.'.original');
    symlink($path.'.original', $path);

    expect(fn () => $resource->handle())->toThrow(RuntimeException::class);
})->with([true, false]);

it('enforces the file count limit without dropping entries', function (): void {
    for ($i = 2; $i < SkillDirectory::MAX_FILES; $i++) {
        file_put_contents($this->path.'/file-'.$i, '');
    }

    expect($this->skill->toArray()['resources'])->toHaveCount(512);
    file_put_contents($this->path.'/one-too-many', '');
    expect(fn () => $this->skill->toArray())->toThrow(InvalidSkillException::class, '512 files');
});

it('enforces total byte size and individual read limits', function (): void {
    $existing = array_sum(array_column($this->skill->toArray()['resources'], 'size'));
    file_put_contents($this->path.'/large', str_repeat('x', SkillDirectory::MAX_SIZE - $existing));
    expect(array_sum(array_column($this->skill->toArray()['resources'], 'size')))->toBe(SkillDirectory::MAX_SIZE);
    file_put_contents($this->path.'/large', 'x', FILE_APPEND);
    expect(fn () => $this->skill->toArray())->toThrow(InvalidSkillException::class, '16 MiB in total');
    file_put_contents($this->path.'/large', str_repeat('x', $existing), FILE_APPEND);
    expect(fn () => $this->skill->read('large'))->toThrow(RuntimeException::class, '16 MiB');
});

it('publishes nested skills independently while retaining complete parent manifests', function (bool $parentFirst): void {
    $nested = $this->path.'/nested';
    mkdir($nested);
    file_put_contents($nested.'/SKILL.md', "---\nname: nested\ndescription: Nested instructions\n---\n");
    $child = new DirectorySkill($nested, 'skill://release-checklist/nested/SKILL.md');
    $skills = $parentFirst ? [$this->skill, $child] : [$child, $this->skill];
    $context = $this->getServerContext(['skills' => $skills]);

    expect($context->skills())->toHaveCount(2)
        ->and($context->resources())->toHaveCount(3);
    $parentManifest = $this->skill->toArray()['resources'];
    $childManifest = $child->toArray()['resources'];
    expect(collect($parentManifest)->firstWhere('uri', $child->uri()))->toBe($childManifest[0]);
    $resource = $context->resources()->first(fn ($resource): bool => $resource->uri() === $child->uri());
    expect($resource->name())->toBe('nested')
        ->and($resource->description())->toBe('Nested instructions')
        ->and($resource->listed())->toBeTrue();
})->with([true, false]);

it('rejects overlapping namespaces backed by different files', function (): void {
    mkdir($this->path.'/nested');
    file_put_contents($this->path.'/nested/SKILL.md', "---\nname: nested\ndescription: Original\n---\n");
    mkdir($this->directory.'/nested');
    file_put_contents($this->directory.'/nested/SKILL.md', "---\nname: nested\ndescription: Different\n---\n");
    $context = $this->getServerContext(['skills' => [$this->skill, new DirectorySkill($this->directory.'/nested', 'skill://release-checklist/nested/SKILL.md')]]);
    expect(fn () => $context->resources())->toThrow(LogicException::class, 'Different skill files');

    foreach (['skills/list' => new ListSkills, 'skills/get' => new GetSkill] as $method => $handler) {
        $request = new JsonRpcRequest(1, $method, ['uri' => $this->skill->uri()]);
        expect(fn (): JsonRpcResponse => $handler->handle($request, $context))->toThrow(LogicException::class, 'Different skill files');
    }
});

it('rejects special files without opening them', function (): void {
    if (! function_exists('posix_mkfifo')) {
        $this->markTestSkipped('Requires POSIX FIFO support.');
    }

    posix_mkfifo($this->path.'/pipe', 0600);
    expect(fn () => $this->skill->toArray())->toThrow(InvalidSkillException::class, 'regular file');
});

it('validates the name even when it matches the directory', function (string $name): void {
    $path = $this->directory.'/'.$name;
    mkdir($path);
    file_put_contents($path.'/SKILL.md', "---\nname: '{$name}'\ndescription: Release\n---\n");
    expect(fn (): array => new DirectorySkill($path)->toArray())->toThrow(InvalidSkillException::class, 'lowercase letters');
})->with(['Uppercase', '-leading', 'trailing-', 'double--hyphen', 'under_score', str_repeat('a', 65)]);

it('accepts lowercase unicode names and counts description characters instead of bytes', function (): void {
    $name = 'publicación';
    $path = $this->directory.'/'.$name;
    mkdir($path);
    file_put_contents($path.'/SKILL.md', "---\nname: {$name}\ndescription: ".str_repeat('é', 1024)."\n---\n");
    $entry = new DirectorySkill($path)->toArray();
    expect($entry['uri'])->toBe('skill://publicaci%C3%B3n/SKILL.md')
        ->and(mb_strlen($entry['frontmatter']['description']))->toBe(1024);
});

it('identifies skill classes by class name in errors', function (): void {
    $skill = new class extends Skill
    {
        public function path(): string
        {
            return '/nonexistent/skill';
        }
    };

    expect(fn (): array => $skill->toArray())->toThrow(InvalidSkillException::class, 'The skill ['.$skill::class.'] is invalid.');
});
