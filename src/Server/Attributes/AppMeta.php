<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Attributes;

use Attribute;
use Laravel\Mcp\Server\Ui\AppMeta as AppMetaData;
use Laravel\Mcp\Server\Ui\Csp;
use Laravel\Mcp\Server\Ui\Enums\Library;
use Laravel\Mcp\Server\Ui\Enums\Permission;
use Laravel\Mcp\Server\Ui\Permissions;

#[Attribute(Attribute::TARGET_CLASS)]
class AppMeta
{
    /**
     * @param  array<int, string>|null  $connectDomains  Domains the app may connect to via fetch, XHR, or WebSocket (CSP connect-src).
     * @param  array<int, string>|null  $resourceDomains  Domains the app may load images, scripts, styles, and fonts from (CSP default-src).
     * @param  array<int, string>|null  $frameDomains  Domains the app may embed as nested iframes (CSP frame-src).
     * @param  array<int, string>|null  $baseUriDomains  Allowed URLs for the document's base element (CSP base-uri).
     * @param  array<int, Permission>|null  $permissions
     * @param  array<int, Library>  $libraries
     */
    public function __construct(
        public readonly ?array $connectDomains = null,
        public readonly ?array $resourceDomains = null,
        public readonly ?array $frameDomains = null,
        public readonly ?array $baseUriDomains = null,
        public readonly ?array $permissions = null,
        public readonly ?bool $prefersBorder = null,
        public readonly ?string $domain = null,
        public readonly array $libraries = [],
    ) {
        //
    }

    public function toAppMeta(): AppMetaData
    {
        $meta = AppMetaData::make();

        if (($csp = $this->getCsp()) instanceof Csp) {
            $meta->csp($csp);
        }

        if ($this->permissions !== null) {
            $meta->permissions(Permissions::make()->allow(...$this->permissions));
        }

        if ($this->prefersBorder !== null) {
            $meta->prefersBorder($this->prefersBorder);
        }

        if ($this->domain !== null) {
            $meta->domain($this->domain);
        }

        if ($this->libraries !== []) {
            $meta->libraries(...$this->libraries);
        }

        return $meta;
    }

    protected function getCsp(): ?Csp
    {
        if (
            $this->connectDomains === null
            && $this->resourceDomains === null
            && $this->frameDomains === null
            && $this->baseUriDomains === null
        ) {
            return null;
        }

        return Csp::make()
            ->connectDomains($this->connectDomains ?? [])
            ->resourceDomains($this->resourceDomains ?? [])
            ->frameDomains($this->frameDomains ?? [])
            ->baseUriDomains($this->baseUriDomains ?? []);
    }
}
