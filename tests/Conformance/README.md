# Conformance

Runs the [MCP conformance suite](https://github.com/modelcontextprotocol/conformance) against this
package's server and client, for protocol revision `2026-07-28`.

## Running

```bash
composer test:conformance           # both suites
composer test:conformance:server
composer test:conformance:client
```

Requires Node (for `npx`) and a free port. Override the defaults with environment variables:

```bash
CONFORMANCE_PORT=9000 \
CONFORMANCE_REVISION=2026-07-28 \
CONFORMANCE_PACKAGE=@modelcontextprotocol/conformance@alpha \
    bash tests/Conformance/run.sh all
```

The runner boots `testbench serve`, points the suite at the `/conformance` route registered by
`WorkbenchServiceProvider`, and tears the server down afterwards. Results land in `results/`, logs in
`logs/`, and a shields.io badge payload in `{server,client}-conformance.json`. All three are ignored
by git.

## Pinning

The suite must be pinned to the `alpha` tag. The stable release (`0.1.16`) only speaks protocol
revisions up to `2025-11-25`, which this package's server no longer accepts — every scenario would
fail at `initialize`. Support for the handshake-less `2026-07-28` revision arrived in
`0.2.0-alpha.x`, which also adds the `--requirements <revision>` flag the runner uses.

`--requirements 2026-07-28` runs the frozen set of scenarios that revision mandates (50 server, 39
client). Do not swap it for `--spec-version`, which only filters the "active" suite down to 20
server scenarios and silently drops everything still pending or draft.

## Baseline

`conformance-baseline.yml` lists scenarios that are expected to fail. The runner exits non-zero when
something outside the baseline fails, when a baselined scenario starts passing (stale entry), or
when a scenario reports a `WARNING` — warnings do not show up in the pass/fail summary but do fail
the gate.

Current state: **server 113/170**, **client 27/97**.

Baselined scenarios fall into three groups.

Unimplemented features:

- `tasks-*` — the tasks/MRTR surface
- `input-required-result-*` — elicitation, sampling and roots round-trips
- `tools-call-with-progress` — progress notifications
- `tools-call-embedded-resource`, `tools-call-mixed-content`, `prompts-get-embedded-resource` —
  there is no embedded-resource content type, so the fixtures return a resource link instead
- most `auth/*` on the client — the harness does not wire `withOAuth()`

Gaps in these fixtures rather than in the package, and cheap to close:

- `json-schema-2020-12` — wants a tool named `json_schema_2020_12_tool`
- `http-custom-header-server-validation` — wants a tool carrying `x-mcp-header` annotations

Worth investigating, likely real:

- `server-stateless` — undeclared client capabilities are not rejected, and
  `MissingRequiredClientCapabilityError` should return HTTP 400
- `http-header-validation` — `Mcp-Name` should tolerate leading and trailing whitespace per
  RFC 9110 §5.5
- `completion-complete` — fails despite `CompletionComplete` being implemented
- `sep-2164-resource-not-found` — the error `data` field should echo the requested URI
- `dns-rebinding-protection` — non-localhost `Host`/`Origin` headers are not rejected
