# Release Notes

## [Unreleased](https://github.com/laravel/mcp/compare/v0.4.0...main)

## [v0.4.0](https://github.com/laravel/mcp/compare/v0.3.4...v0.4.0) - 2025-12-01

### What's Changed

* Add Annotation Support on Resources by [@pushpak1300](https://github.com/pushpak1300) in https://github.com/laravel/mcp/pull/111
* Add structuredContent & outputSchema Support by [@macbookandrew](https://github.com/macbookandrew) in https://github.com/laravel/mcp/pull/83
* Standardise `Role` case names by [@pushpak1300](https://github.com/pushpak1300) in https://github.com/laravel/mcp/pull/116
* Test Improvements by [@crynobone](https://github.com/crynobone) in https://github.com/laravel/mcp/pull/115
* PHP 8.5 Compatibility by [@pushpak1300](https://github.com/pushpak1300) in https://github.com/laravel/mcp/pull/114
* Fix casing for keys in OAuthRegisterController response by [@pushpak1300](https://github.com/pushpak1300) in https://github.com/laravel/mcp/pull/117
* Update JsonSchema usage by [@pushpak1300](https://github.com/pushpak1300) in https://github.com/laravel/mcp/pull/120
* Add Support For Resource Templates by [@pushpak1300](https://github.com/pushpak1300) in https://github.com/laravel/mcp/pull/113
* Remove unused `resource-template` stub and update `JsonSchema` import by [@pushpak1300](https://github.com/pushpak1300) in https://github.com/laravel/mcp/pull/122

### New Contributors

* [@macbookandrew](https://github.com/macbookandrew) made their first contribution in https://github.com/laravel/mcp/pull/83
* [@crynobone](https://github.com/crynobone) made their first contribution in https://github.com/laravel/mcp/pull/115

### Breaking Change

#### 1. Case Name Updates (https://github.com/laravel/mcp/pull/116)

Applications referencing the previous case names will need manual updates.

**Required changes**

* `Role::ASSISTANT` should be updated to `Role::Assistant`
* `Role::USER` should be updated to `Role::User`

Make sure your codebase reflects these changes before upgrading to avoid build or runtime errors.

#### 2. JsonSchema Contract Change (https://github.com/laravel/mcp/pull/120)

Tool implementations that explicitly type hint `Illuminate\JsonSchema\JsonSchema` in their `schema()` or `outputSchema()` methods must update to use the contract interface `Illuminate\Contracts\JsonSchema\JsonSchema`.

##### Migration Guide

**Before**

```php
use Illuminate\JsonSchema\JsonSchema;

public function schema(JsonSchema $schema): array
{
    //
}


```
**After**

```php
use Illuminate\Contracts\JsonSchema\JsonSchema;

public function schema(JsonSchema $schema): array
{
    //
}


```
This affects only custom tool classes that override the schema methods. The update is minimal, requiring only the import change to the contract interface.

**Full Changelog**: https://github.com/laravel/mcp/compare/v0.3.4...v0.4.0

## [v0.3.4](https://github.com/laravel/mcp/compare/v0.3.3...v0.3.4) - 2025-11-18

* Add _meta support  by [@pushpak1300](https://github.com/pushpak1300) in https://github.com/laravel/mcp/pull/106
* Remove non-spec fields from resource content responses by [@pushpak1300](https://github.com/pushpak1300) in https://github.com/laravel/mcp/pull/110

## [v0.3.3](https://github.com/laravel/mcp/compare/v0.3.2...v0.3.3) - 2025-11-11

* Add MCP service provider to testbench config by [@zacksmash](https://github.com/zacksmash) in https://github.com/laravel/mcp/pull/100
* Fix client_name rename in oauth registrar by [@mikebouwmans](https://github.com/mikebouwmans) in https://github.com/laravel/mcp/pull/104
* fix: allow multi-segment issuer paths by [@isaac-bowen](https://github.com/isaac-bowen) in https://github.com/laravel/mcp/pull/105

## [v0.3.2](https://github.com/laravel/mcp/compare/v0.3.1...v0.3.2) - 2025-10-29

### What's Changed

* Ensure the property field exists in tool input schemas by [@pushpak1300](https://github.com/pushpak1300) in https://github.com/laravel/mcp/pull/97

### New Contributors

* [@pushpak1300](https://github.com/pushpak1300) made their first contribution in https://github.com/laravel/mcp/pull/97

**Full Changelog**: https://github.com/laravel/mcp/compare/v0.3.1...v0.3.2

## [v0.3.1](https://github.com/laravel/mcp/compare/v0.3.0...v0.3.1) - 2025-10-24

* refactor: move to first class callable by [@ashleyhindle](https://github.com/ashleyhindle) in https://github.com/laravel/mcp/pull/94
* Cast `client_id` to string in JSON response by [@mostafa-rz](https://github.com/mostafa-rz) in https://github.com/laravel/mcp/pull/93
* Feature: adds security to the OAuth registration endpoint by [@jsandfordhughescoop](https://github.com/jsandfordhughescoop) in https://github.com/laravel/mcp/pull/87

## [v0.3.0](https://github.com/laravel/mcp/compare/v0.2.1...v0.3.0) - 2025-10-07

* Add assertDontSee() to TestResponse and extend test coverage by [@mattwells-coex](https://github.com/mattwells-coex) in https://github.com/laravel/mcp/pull/74
* Fix tool annotation type error when using custom Attributes by [@Daanra](https://github.com/Daanra) in https://github.com/laravel/mcp/pull/75
* Add Macroable and Conditionable traits to some core classes by [@mattwells-coex](https://github.com/mattwells-coex) in https://github.com/laravel/mcp/pull/76

## [v0.2.1](https://github.com/laravel/mcp/compare/v0.2.0...v0.2.1) - 2025-09-24

* feat: default the MCP inspector to 'stdio' when inspecting a local server by [@ashleyhindle](https://github.com/ashleyhindle) in https://github.com/laravel/mcp/pull/52
* Fix error message formatting in InspectorCommand by [@aymanebouljam](https://github.com/aymanebouljam) in https://github.com/laravel/mcp/pull/53
* InspectorCommand error message improvement by [@szabi-bc](https://github.com/szabi-bc) in https://github.com/laravel/mcp/pull/55
* Uses nowdoc for LLMs by [@nunomaduro](https://github.com/nunomaduro) in https://github.com/laravel/mcp/pull/56
* Remove `.junie` Detritus by [@yitzwillroth](https://github.com/yitzwillroth) in https://github.com/laravel/mcp/pull/59
* Enhance documentation consistency and Tool class annotations by [@aymanebouljam](https://github.com/aymanebouljam) in https://github.com/laravel/mcp/pull/57
* add basic sessionid support by [@ashleyhindle](https://github.com/ashleyhindle) in https://github.com/laravel/mcp/pull/58
* Fixes `artisan optimize` command by [@nunomaduro](https://github.com/nunomaduro) in https://github.com/laravel/mcp/pull/66
* [0.x] Adds support for custom request classes by [@nunomaduro](https://github.com/nunomaduro) in https://github.com/laravel/mcp/pull/63

## [v0.2.0](https://github.com/laravel/mcp/compare/v0.1.0...v0.2.0) - 2025-09-18

- First official "beta" release of Laravel MCP package.

## v0.1.0 (202x-xx-xx)

Initial pre-release.
