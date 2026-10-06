# Serving Agent Skills

- [Introduction](#introduction)
- [Creating Skills](#creating-skills)
    - [Registering Skills](#registering-skills)
    - [Conditional Registration](#conditional-registration)
- [Discovery and Retrieval](#discovery-and-retrieval)
    - [Skill Entries](#skill-entries)
    - [Reading Files](#reading-files)
    - [Caching](#caching)
    - [Errors](#errors)
- [Skill URIs](#skill-uris)
    - [Nested Skills](#nested-skills)
- [Frontmatter](#frontmatter)
- [File Safety](#file-safety)
- [Supported Scope](#supported-scope)

<a name="introduction"></a>
## Introduction

Laravel MCP servers may serve [Agent Skills](https://agentskills.io/specification) to MCP clients through the [`io.modelcontextprotocol/skills` extension](https://skills.extensions.modelcontextprotocol.io/specification/stable/skills). A skill is a directory containing a `SKILL.md` file and any supporting files. The server lists its skills, describes each one with a complete file manifest, and serves the files as MCP resources.

This feature only distributes skills over MCP. It does not install Laravel Boost skills and it does not invoke Laravel AI SDK agents.

<a name="creating-skills"></a>
## Creating Skills

A skill is a directory that follows the Agent Skills format:

```text
resources/skills/release-checklist/
├── SKILL.md
└── references/
    └── checklist.md
```

The `SKILL.md` file must begin with YAML frontmatter containing at least a `name` and a `description`. The `name` must match the directory name:

```markdown
---
name: release-checklist
description: Prepare a release using the project's release checklist.
metadata:
  version: "1.0"
---

Read [the checklist](references/checklist.md) before preparing a release.
```

Next, define a class that extends `Laravel\Mcp\Server\Skill` and returns the directory from its `path` method:

```php
namespace App\Mcp\Skills;

use Laravel\Mcp\Server\Skill;

class ReleaseChecklist extends Skill
{
    public function path(): string
    {
        return resource_path('skills/release-checklist');
    }
}
```

<a name="registering-skills"></a>
### Registering Skills

Register the skill in the `$skills` property of your server. Like tools, resources, and prompts, you may list class names or instances. Class names are resolved through the service container:

```php
use App\Mcp\Skills\ReleaseChecklist;
use Laravel\Mcp\Server;

class ProjectServer extends Server
{
    protected array $skills = [
        ReleaseChecklist::class,
    ];
}
```

When a skill needs no attributes or `shouldRegister` method, you may skip the class and register a directory directly with `Skill::fromDirectory` from your server's `boot` method. The second argument is an optional URI; see [Skill URIs](#skill-uris):

```php
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Skill;

class ProjectServer extends Server
{
    protected function boot(): void
    {
        $this->skills[] = Skill::fromDirectory(resource_path('skills/release-checklist'));
    }
}
```

`fromDirectory` does not read the directory. Like skills registered by class, the directory is validated when a client lists, retrieves, or reads the skill, and an empty path is rejected at that point.

Registering a skill enables the extension and declares the `resources` capability if the server does not already declare it. Servers without skills are unchanged: they do not advertise the extension, and `skills/list` and `skills/get` are not available.

To advertise the extension with an empty catalog, add `Laravel\Mcp\Enums\Extension::Skills` to the server's `$extensions` property.

<a name="conditional-registration"></a>
### Conditional Registration

Skills support the same `shouldRegister` method as other primitives. A skill whose `shouldRegister` method returns `false` is omitted from `skills/list`, cannot be retrieved with `skills/get`, and does not contribute resources:

```php
use Laravel\Mcp\Request;

public function shouldRegister(Request $request): bool
{
    return $request->user()?->can('prepare-releases') ?? false;
}
```

`shouldRegister` decides whether a skill is registered. It is not a per-file access control: when another registered skill's directory contains the same files, they remain available through that skill. See [Nested Skills](#nested-skills).

<a name="discovery-and-retrieval"></a>
## Discovery and Retrieval

Clients discover the extension through `server/discover`. The result's `capabilities.extensions` contains `io.modelcontextprotocol/skills` with an empty object. Clients then use the following methods:

| Method | Behavior |
| --- | --- |
| `skills/list` | Returns the entries of all registered skills, using the server's cursor pagination. |
| `skills/get` | Returns the entry of one skill, identified by the full URI of its `SKILL.md`. |
| `resources/list` | Includes skill files alongside the server's other resources. |
| `resources/read` | Returns the contents of one skill file by its URI. |

`skills/list` accepts the same `cursor` parameter as the server's other list methods and returns `nextCursor` when more pages exist. An entry is never split across pages. `skills/get` does not require a prior `skills/list` request.

<a name="skill-entries"></a>
### Skill Entries

`skills/list` returns entries under `skills`, and `skills/get` returns one entry under `skill`. A skill is requested by the URI of its `SKILL.md`. The extension uses base protocol `2026-07-28`, so requests carry the same `_meta` fields and HTTP headers as the server's other methods:

```json
{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "skills/get",
    "params": {
        "uri": "skill://release-checklist/SKILL.md",
        "_meta": {
            "io.modelcontextprotocol/protocolVersion": "2026-07-28",
            "io.modelcontextprotocol/clientCapabilities": {}
        }
    }
}
```

Each entry has three fields:

| Field | Contents |
| --- | --- |
| `uri` | The URI of the skill's `SKILL.md`. |
| `frontmatter` | The parsed [frontmatter](#frontmatter) of `SKILL.md`. |
| `resources` | The manifest: one item per file with its `uri`, `digest`, and `size`. |

The manifest is always complete. It lists every file in the skill directory, including `SKILL.md`, dotfiles, and files in nested directories. The `digest` is the SHA-256 hash of the file's bytes, formatted as `sha256:` followed by lowercase hexadecimal, and `size` is the file's length in bytes.

Manifests are computed from the filesystem on every `skills/list` and `skills/get` request. If a file changes after a client has fetched an entry, the client's digest check will fail until it fetches the entry again.

<a name="reading-files"></a>
### Reading Files

Each file is served through `resources/read` at the URI published in the manifest. Valid UTF-8 content is returned as `text`, unless it contains C0 control characters other than tabs and line breaks, such as NUL bytes; all other content is returned as a base64 encoded `blob`. In both cases the client receives the file's original bytes.

Files with an `.md` extension use the `text/markdown` MIME type. The MIME type of other files is detected from their contents. The server never executes scripts or any other skill file; they are served as data.

<a name="caching"></a>
### Caching

Successful `skills/list` and `skills/get` results include `resultType: "complete"`, `ttlMs`, and `cacheScope`. The cache fields default to `0` and `private`. You may change them with the server's `Cacheable` attribute or `cacheHints` method, as with the other cacheable methods:

```php
use Laravel\Mcp\Enums\CacheScope;
use Laravel\Mcp\Server\Attributes\Cacheable;

protected function cacheHints(): array
{
    return [
        'skills/list' => new Cacheable(ttlMs: 60000, scope: CacheScope::Public),
    ];
}
```

Only use the public scope when every caller receives the same skills and the same content.

<a name="errors"></a>
### Errors

`skills/get` returns a JSON-RPC `-32602` error when the URI is missing, malformed, or does not identify a registered skill's `SKILL.md`. `resources/read` returns `-32602` for any URI that is not a published file.

An invalid skill, such as a missing directory or invalid frontmatter, causes `skills/list` and `skills/get` to fail with an internal error instead of returning an incomplete entry. The same applies when a skill's files conflict with other registered resources. Skill files are part of the server's resource list, so an invalid skill also affects `resources/list` and `resources/read`; validate skill directories before deploying them.

<a name="skill-uris"></a>
## Skill URIs

By default, a skill's URI is `skill://<directory-name>/SKILL.md`, and each file's URI is its path relative to the skill directory. For the example above, the URIs are `skill://release-checklist/SKILL.md` and `skill://release-checklist/references/checklist.md`. Each path segment is percent-encoded, so clients should use the published URIs as returned.

You may use the `Uri` attribute, or the `$uri` property, to add a prefix or use another scheme:

```php
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Skill;

#[Uri('skill://engineering/release-checklist/SKILL.md')]
class ReleaseChecklist extends Skill
{
    public function path(): string
    {
        return resource_path('skills/release-checklist');
    }
}
```

The URI must end in `<name>/SKILL.md`, where `<name>` is the skill's name, and must not contain a query string or fragment.

Skills are identified by URI, not by name. Two skills may share a name when their URIs differ, but two skills may not share a URI, and a regular resource may not use the URI of a skill file.

<a name="nested-skills"></a>
### Nested Skills

A skill directory may contain another skill in a subdirectory. The nested skill's files, including its `SKILL.md`, are part of the enclosing skill's manifest as supporting files.

Nested skills are not registered automatically. To publish one as a skill of its own, register a second class whose `path` method returns the nested directory and whose URI extends the enclosing skill's URI:

```php
#[Uri('skill://release-checklist/hotfix/SKILL.md')]
class Hotfix extends Skill
{
    public function path(): string
    {
        return resource_path('skills/release-checklist/hotfix');
    }
}
```

`skills/list` remains a flat list with one entry per registered skill.

When a nested skill is not registered, or its `shouldRegister` method returns `false`, its files are still served as supporting files of the enclosing skill. If two skills need different access rules, keep them in separate directories.

<a name="frontmatter"></a>
## Frontmatter

The `frontmatter` of an entry contains every field of the `SKILL.md` frontmatter. The server validates the fields defined by the Agent Skills specification:

| Field | Rule |
| --- | --- |
| `name` | Required. At most 64 characters; lowercase letters, numbers, and single hyphens. Must match the directory name. |
| `description` | Required. Between 1 and 1024 characters. |
| `license` | Optional string. |
| `compatibility` | Optional string between 1 and 500 characters. |
| `allowed-tools` | Optional string. |
| `metadata` | Optional mapping of strings to strings. |

All other fields are passed through unchanged, provided their values can be represented as JSON. Mappings and lists are preserved as JSON objects and arrays.

Dates and timestamps must be quoted. An unquoted YAML date, including one in a nested field, is rejected instead of being converted to another type:

```yaml
published-at: "2026-10-05"
```

<a name="file-safety"></a>
## File Safety

Every regular file beneath a registered directory is published, including dotfiles. Only register directories that are dedicated to a skill and controlled by your application. Do not register a project root, an upload directory, or any directory that contains credentials.

A skill is only published when its directory passes the following checks, which run each time a manifest is built:

- The skill directory, and every file and directory beneath it, must not be a symbolic link.
- Only regular files and directories are allowed. Any other file type causes the skill to fail.
- A skill may contain at most 512 files and 16 MiB in total.

`resources/read` only serves URIs that match a published file. Path traversal, encoded separators, query strings, fragments, and directory URIs do not resolve to files. Before returning a file, the read checks again that it is a regular file inside the skill directory, that no part of its path is a symbolic link, and that it does not exceed 16 MiB.

These checks are not a substitute for filesystem permissions and cannot rule out races with a process that is able to write to the directory while it is being served. Keep skill directories read-only to untrusted users and, when possible, deploy changes by replacing the whole directory atomically.

<a name="supported-scope"></a>
## Supported Scope

This implementation covers the required server side of the extension: the capability declaration, `skills/list`, `skills/get`, complete resource manifests, and skill files served as resources.

The following are not implemented:

- The optional `directoryRead` capability and the `resources/directory/read` method.
- Dynamic manifests (`"resources": "dynamic"`).
- Client or host behavior, such as digest verification, user approval, skill activation, or execution. These remain the responsibility of the host application; digests show that content matches the entry, not that the server is trusted.
