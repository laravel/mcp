# Serving Agent Skills

Laravel MCP can publish directories containing `SKILL.md` and supporting files through the official [`io.modelcontextprotocol/skills` extension](https://skills.extensions.modelcontextprotocol.io/specification/stable/skills) (SEP-2640, base protocol `2026-07-28`). This is server-side distribution over MCP; it does not install Laravel Boost skills or invoke Laravel AI SDK agents.

## Registering skills

Create a directory using the [Agent Skills format](https://agentskills.io/specification):

```text
resources/skills/release-checklist/
├── SKILL.md
└── references/
    └── checklist.md
```

Its `SKILL.md` might contain:

```markdown
---
name: release-checklist
description: Prepare a release using the project's release checklist.
metadata:
  version: "1.0"
---

Read [the checklist](references/checklist.md) before preparing a release.
```

Define a skill class whose `path` method returns that directory:

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

Register the class in your server's `$skills` property:

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

Instances are also accepted, including instances added in `boot`. Like tools and resources, skills support container injection and a `shouldRegister` method. A skill excluded by `shouldRegister` is unavailable through both skill methods and its generated resources. Use your existing route authentication and authorization policies when deciding eligibility.

Registration enables the extension automatically and ensures the Resources capability is present. Servers without registered skills retain their existing behavior. To advertise an intentionally empty catalog, include `Laravel\Mcp\Enums\Extension::Skills` in the server's `$extensions` property.

## Discovery and retrieval

The server advertises `capabilities.extensions["io.modelcontextprotocol/skills"]` as `{}` in `server/discover`. Clients use the existing protocol metadata and HTTP header conventions.

| Method | Behavior |
| --- | --- |
| `skills/list` | Lists complete entries using the server's existing cursor pagination. |
| `skills/get` | Accepts `uri`, identifying the full `SKILL.md` resource; works without a preceding list request. |
| `resources/list` | Includes skill files alongside the server's ordinary resources. |
| `resources/read` | Reads each file at its published URI, preserving the original bytes. |

For this example, the entry URI is `skill://release-checklist/SKILL.md`; its supporting file is `skill://release-checklist/references/checklist.md`. Path segments are percent encoded individually. Clients should use the returned URIs exactly.

Each entry contains `uri`, `frontmatter`, and `resources`. The frontmatter retains every authored field, including unknown fields and nested JSON object/array distinctions. The resource manifest contains each file's URI, SHA-256 digest (`sha256:` followed by lowercase hexadecimal), and byte size. It includes hidden files and files in nested directories. A skill's manifest stays together on a single page.

Text is returned as resource `text`; binary content uses base64 `blob`. `SKILL.md` and other Markdown files use `text/markdown`; other MIME types are detected from their contents. Scripts are served as data and are never executed by the server.

The required `resultType`, `ttlMs`, and `cacheScope` fields use the package's existing response handling. Skill list/get responses default to `ttlMs: 0` and `cacheScope: "private"`. Existing server `#[Cacheable]` attributes and `cacheHints()` entries for `skills/list` and `skills/get` can override those defaults. Only advertise public caching when the returned catalog and content are identical for all callers.

Unknown skill URIs and unserved file URIs return JSON-RPC `-32602`. Invalid server-side skill configuration fails instead of publishing an incomplete entry. Internal failures follow the server's existing error handling.

## Namespaces and nested skills

Use the existing `Uri` attribute, or the protected `$uri` property, to choose a prefix or another resource scheme:

```php
use Laravel\Mcp\Server\Attributes\Uri;

#[Uri('skill://engineering/release-checklist/SKILL.md')]
class ReleaseChecklist extends Skill
{
    public function path(): string
    {
        return resource_path('skills/release-checklist');
    }
}
```

The URI must end in `<name>/SKILL.md`. The frontmatter name must match the local directory name. Different skills may share a name when their URIs differ; duplicate entry URIs are rejected. Ordinary resources must not reuse a skill file's URI.

A nested directory containing another `SKILL.md` is included as supporting content in the enclosing manifest. To publish it as an independent skill, register another class pointing to the nested directory and give it the corresponding nested URI. Discovery remains a flat list. The server does not automatically register nested skills. Excluding an independent nested skill does not hide its files from an eligible enclosing skill; split directories when access policies differ.

## File safety and deployment

Publish dedicated, application-controlled directories. Every regular file beneath a registered directory is exposed, including dotfiles: do not point skills at a project root, user upload directory, or a directory containing credentials.

The implementation rejects symbolic links, nonregular files, invalid frontmatter, and skills exceeding 512 files or 16 MiB total. It validates required fields, naming rules, and the types and lengths of known optional fields. Unknown frontmatter fields pass through. YAML parsing never enables PHP object deserialization or constant evaluation; values must be JSON representable.

Reads resolve only registered file URIs and recheck the filesystem boundary, file type, and symlinks before reading. URI traversal, encoded separators, arbitrary filesystem paths, and directory reads cannot be used to access extra files. Keep the published directories protected from concurrent untrusted writes; these checks do not replace operating-system access controls. Deploy complete directories atomically when possible.

Manifests are refreshed on each list/get request. Changes between discovery and reading may cause a client's digest verification to fail; the client refreshes with `skills/get`. Digests establish content consistency, not trust in the server. Host applications remain responsible for origin isolation, digest verification, per-skill execution approval, and gating permission-related frontmatter such as `allowed-tools`. Reading a resource does not itself activate a skill.

## Supported scope

The mandatory server methods, resource transport, discovery declaration, entry shape, and caching fields are implemented. Optional directory browsing (`resources/directory/read` and `directoryRead: true`) and dynamically generated manifests (`resources: "dynamic"`) are not implemented. This feature does not add client-side skill activation, local installation, execution, or approval management.

The implementation follows the stable [extension specification](https://github.com/modelcontextprotocol/ext-skills/blob/main/specification/stable/skills.mdx), rather than the working group's archived proposals.
