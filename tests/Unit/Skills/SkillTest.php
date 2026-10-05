<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Skill;
use Symfony\Component\Yaml\Exception\ParseException;
use Tests\Fixtures\TestSkill;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/mcp-skills-'.bin2hex(random_bytes(8));
    $this->files = new Filesystem;
    $this->files->copyDirectory(__DIR__.'/../../Fixtures/Skills/release-checklist', $this->directory.'/release-checklist');

    $this->skill = new TestSkill($this->directory.'/release-checklist');
});

afterEach(function (): void {
    $this->files->deleteDirectory($this->directory);
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
        ->and($this->skill->description())->toBe($entry['frontmatter']['description'])
        ->and($this->skill->toMethodCall())->toBe(['uri' => $entry['uri']]);
});

it('builds a complete sorted manifest including hidden and nested files from raw bytes', function (): void {
    mkdir($this->skill->path().'/nested');
    file_put_contents($this->skill->path().'/nested/SKILL.md', "---\nname: nested\ndescription: Nested instructions\n---\n");
    file_put_contents($this->skill->path().'/.hidden', "hidden\r\n");
    file_put_contents($this->skill->path().'/references/a space #%?.bin', "\0\xffbinary\r\n");

    $entry = $this->skill->toArray();

    expect(array_column($entry['resources'], 'uri'))->toBe([
        'skill://release-checklist/.hidden',
        'skill://release-checklist/SKILL.md',
        'skill://release-checklist/nested/SKILL.md',
        'skill://release-checklist/references/a%20space%20%23%25%3F.bin',
        'skill://release-checklist/references/checklist.md',
    ]);

    foreach ($entry['resources'] as $resource) {
        $relative = rawurldecode(substr($resource['uri'], strlen('skill://release-checklist/')));
        $contents = file_get_contents($this->skill->path().'/'.$relative);
        expect($resource['size'])->toBe(strlen($contents))
            ->and($resource['digest'])->toBe('sha256:'.hash('sha256', $contents));
    }
});

it('supports organizational prefixes and other URI schemes', function (string $uri): void {
    $skill = new TestSkill($this->skill->path(), $uri);
    expect($skill->toArray()['uri'])->toBe($uri)
        ->and($skill->toArray()['resources'][1]['uri'])->toBe(substr($uri, 0, -8).'references/checklist.md');
})->with(['skill://team/releases/release-checklist/SKILL.md', 'docs://team/release-checklist/SKILL.md']);

it('supports URI attributes', function (): void {
    $skill = new #[Uri('skill://team/release-checklist/SKILL.md')] class extends TestSkill {};
    expect($skill->toArray()['uri'])->toBe('skill://team/release-checklist/SKILL.md');
});

it('rejects invalid skill URIs', function (string $uri): void {
    $skill = new TestSkill($this->skill->path(), $uri);
    expect(fn (): array => $skill->toArray())->toThrow(RuntimeException::class, 'The skill URI');
})->with(['skill://wrong/SKILL.md', 'skill://release-checklist', 'skill://release-checklist/SKILL.md?x=1', 'skill://release-checklist/SKILL.md#part', 'skill:///release-checklist/SKILL.md', 'skill://team/../release-checklist/SKILL.md', 'skill://%2e%2e/release-checklist/SKILL.md', 'skill://bad%2Fprefix/release-checklist/SKILL.md', 'skill://bad%5Cprefix/release-checklist/SKILL.md', 'skill://bad%00prefix/release-checklist/SKILL.md', 'skill://bad%ZZprefix/release-checklist/SKILL.md']);

it('rejects invalid frontmatter', function (string $contents): void {
    file_put_contents($this->skill->path().'/SKILL.md', $contents);
    expect(fn () => $this->skill->toArray())->toThrow(RuntimeException::class);
})->with([
    'missing frontmatter' => 'No frontmatter',
    'missing closing delimiter' => "---\nname: release-checklist\ndescription: Release\n",
    'missing name' => "---\ndescription: Release\n---\n",
    'numeric name' => "---\nname: 123\ndescription: Release\n---\n",
    'mismatched directory' => "---\nname: different\ndescription: Release\n---\n",
    'uppercase name' => "---\nname: Release-checklist\ndescription: Release\n---\n",
    'double hyphen' => "---\nname: release--checklist\ndescription: Release\n---\n",
    'long name' => "---\nname: ".str_repeat('x', 65)."\ndescription: Release\n---\n",
    'empty description' => "---\nname: release-checklist\ndescription: ' '\n---\n",
    'missing description' => "---\nname: release-checklist\n---\n",
    'long description' => "---\nname: release-checklist\ndescription: ".str_repeat('x', 1025)."\n---\n",
    'invalid utf8' => "---\nname: release-checklist\ndescription: Release\n---\n\xff",
    'nonstring license' => "---\nname: release-checklist\ndescription: Release\nlicense: true\n---\n",
    'nonstring allowed-tools' => "---\nname: release-checklist\ndescription: Release\nallowed-tools: [Read]\n---\n",
    'empty compatibility' => "---\nname: release-checklist\ndescription: Release\ncompatibility: ''\n---\n",
    'long compatibility' => "---\nname: release-checklist\ndescription: Release\ncompatibility: ".str_repeat('x', 501)."\n---\n",
    'metadata sequence' => "---\nname: release-checklist\ndescription: Release\nmetadata: []\n---\n",
    'metadata value' => "---\nname: release-checklist\ndescription: Release\nmetadata: { version: 1 }\n---\n",
]);

it('uses a YAML parser for multiline strings and preserves CRLF bytes', function (): void {
    $contents = "---\r\nname: release-checklist\r\ndescription: >-\r\n  Prepare a release\r\n  with care.\r\n---\r\n# Steps\r\n";
    file_put_contents($this->skill->path().'/SKILL.md', $contents);
    $entry = $this->skill->toArray();
    expect($entry['frontmatter']['description'])->toBe('Prepare a release with care.')
        ->and($entry['resources'][0]['digest'])->toBe('sha256:'.hash('sha256', $contents))
        ->and($this->skill->read('SKILL.md'))->toBe($contents);
});

it('rejects malformed YAML and PHP object tags', function (string $yaml): void {
    file_put_contents($this->skill->path().'/SKILL.md', "---\nname: release-checklist\ndescription: Release\n{$yaml}\n---\n");
    expect(fn () => $this->skill->toArray())->toThrow(ParseException::class);
})->with(['broken: [', "object: !php/object 'O:8:\"stdClass\":0:{}'"]);

it('rejects values that JSON cannot represent', function (): void {
    file_put_contents($this->skill->path().'/SKILL.md', "---\nname: release-checklist\ndescription: Release\ncustom: .inf\n---\n");
    expect(fn () => $this->skill->toArray())->toThrow(JsonException::class);
});

it('requires an existing directory and SKILL.md', function (): void {
    expect(fn (): array => (new TestSkill($this->directory.'/missing'))->toArray())->toThrow(RuntimeException::class);
    unlink($this->skill->path().'/SKILL.md');
    expect(fn () => $this->skill->toArray())->toThrow(RuntimeException::class);
});

it('rejects traversal paths independently of resource resolution', function (string $path): void {
    expect(fn () => $this->skill->read($path))->toThrow(RuntimeException::class);
})->with(['../secret', '/etc/passwd', 'references/../../secret', 'references/../SKILL.md', 'references\\file', "references/\0file", '', 'references//checklist.md']);

it('rejects symbolic links including links within the skill and broken links', function (string $target): void {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('Creating symbolic links requires elevated privileges on Windows.');
    }

    symlink(match ($target) {
        'inside' => $this->skill->path().'/SKILL.md',
        'outside' => $this->directory,
        default => $this->directory.'/missing',
    }, $this->skill->path().'/link');

    expect(fn () => $this->skill->toArray())->toThrow(RuntimeException::class)
        ->and(fn () => $this->skill->read('link'))->toThrow(RuntimeException::class);
})->with(['inside', 'outside', 'broken']);

it('rejects a symbolic link used as the root', function (): void {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('Creating symbolic links requires elevated privileges on Windows.');
    }

    symlink($this->skill->path(), $this->directory.'/linked');
    expect(fn (): array => (new TestSkill($this->directory.'/linked'))->toArray())->toThrow(RuntimeException::class);
});

it('revalidates files and ancestor directories after resource discovery', function (bool $directory): void {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('Creating symbolic links requires elevated privileges on Windows.');
    }

    $resource = $this->skill->resources()[1];
    $path = $this->skill->path().($directory ? '/references' : '/references/checklist.md');
    rename($path, $path.'.original');
    symlink($path.'.original', $path);

    expect(fn () => $resource->handle())->toThrow(RuntimeException::class);
})->with([true, false]);

it('enforces the file count limit without dropping entries', function (): void {
    for ($i = 2; $i < Skill::MAX_RESOURCES; $i++) {
        file_put_contents($this->skill->path().'/file-'.$i, '');
    }

    expect($this->skill->toArray()['resources'])->toHaveCount(512);
    file_put_contents($this->skill->path().'/one-too-many', '');
    expect(fn () => $this->skill->toArray())->toThrow(RuntimeException::class, '512 files');
});

it('enforces total byte size and individual read limits', function (): void {
    $existing = array_sum(array_column($this->skill->toArray()['resources'], 'size'));
    file_put_contents($this->skill->path().'/large', str_repeat('x', Skill::MAX_SIZE - $existing));
    expect(array_sum(array_column($this->skill->toArray()['resources'], 'size')))->toBe(Skill::MAX_SIZE);
    file_put_contents($this->skill->path().'/large', 'x', FILE_APPEND);
    expect(fn () => $this->skill->toArray())->toThrow(RuntimeException::class, '16 MiB in total');
    file_put_contents($this->skill->path().'/large', str_repeat('x', $existing), FILE_APPEND);
    expect(fn () => $this->skill->read('large'))->toThrow(RuntimeException::class, '16 MiB');
});

it('publishes nested skills independently while retaining complete parent manifests', function (bool $parentFirst): void {
    $nested = $this->skill->path().'/nested';
    mkdir($nested);
    file_put_contents($nested.'/SKILL.md', "---\nname: nested\ndescription: Nested instructions\n---\n");
    $child = new TestSkill($nested, 'skill://release-checklist/nested/SKILL.md');
    $skills = $parentFirst ? [$this->skill, $child] : [$child, $this->skill];
    $context = $this->getServerContext(['skills' => $skills]);

    expect($context->skills())->toHaveCount(2)
        ->and($context->resources())->toHaveCount(3);
    $parentManifest = $this->skill->toArray()['resources'];
    $childManifest = $child->toArray()['resources'];
    expect(collect($parentManifest)->firstWhere('uri', $child->uri()))->toBe($childManifest[0]);
    $resource = $context->resources()->first(fn ($resource): bool => $resource->uri() === $child->uri());
    expect($resource->name())->toBe('nested')
        ->and($resource->description())->toBe('Nested instructions');
})->with([true, false]);

it('rejects overlapping namespaces backed by different files', function (): void {
    mkdir($this->skill->path().'/nested');
    file_put_contents($this->skill->path().'/nested/SKILL.md', "---\nname: nested\ndescription: Original\n---\n");
    mkdir($this->directory.'/nested');
    file_put_contents($this->directory.'/nested/SKILL.md', "---\nname: nested\ndescription: Different\n---\n");
    $context = $this->getServerContext(['skills' => [$this->skill, new TestSkill($this->directory.'/nested', 'skill://release-checklist/nested/SKILL.md')]]);
    expect(fn () => $context->resources())->toThrow(InvalidArgumentException::class, 'Different skill files');
});

it('rejects special files without opening them', function (): void {
    if (! function_exists('posix_mkfifo')) {
        $this->markTestSkipped('Requires POSIX FIFO support.');
    }

    posix_mkfifo($this->skill->path().'/pipe', 0600);
    expect(fn () => $this->skill->toArray())->toThrow(RuntimeException::class, 'regular files');
});

it('requires frontmatter to be a mapping', function (): void {
    file_put_contents($this->skill->path().'/SKILL.md', "---\n- name\n- description\n---\n");
    expect(fn () => $this->skill->toArray())->toThrow(RuntimeException::class, 'YAML mapping');
});

it('validates the name even when it matches the directory', function (string $name): void {
    $path = $this->directory.'/'.$name;
    mkdir($path);
    file_put_contents($path.'/SKILL.md', "---\nname: '{$name}'\ndescription: Release\n---\n");
    expect(fn (): array => (new TestSkill($path))->toArray())->toThrow(RuntimeException::class, 'naming rules');
})->with(['Uppercase', '-leading', 'trailing-', 'double--hyphen', 'under_score', str_repeat('a', 65)]);

it('accepts lowercase unicode names and counts description characters instead of bytes', function (): void {
    $name = 'publicación';
    $path = $this->directory.'/'.$name;
    mkdir($path);
    file_put_contents($path.'/SKILL.md', "---\nname: {$name}\ndescription: ".str_repeat('é', 1024)."\n---\n");
    $entry = (new TestSkill($path))->toArray();
    expect($entry['uri'])->toBe('skill://publicaci%C3%B3n/SKILL.md')
        ->and(mb_strlen($entry['frontmatter']['description']))->toBe(1024);
});
