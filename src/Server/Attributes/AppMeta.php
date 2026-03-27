<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Attributes;

use Attribute;
use Laravel\Mcp\Server\Ui\AppMeta as AppMetaData;
use Laravel\Mcp\Server\Ui\Csp;
use Laravel\Mcp\Server\Ui\Enum\Permission;
use Laravel\Mcp\Server\Ui\Permissions;

#[Attribute(Attribute::TARGET_CLASS)]
class AppMeta
{
    /**
     * @param  array<int, string>|null  $connectDomains
     * @param  array<int, string>|null  $resourceDomains
     * @param  array<int, string>|null  $frameDomains
     * @param  array<int, string>|null  $baseUriDomains
     * @param  array<int, Permission>|null  $permissions
     */
    public function __construct(
        public readonly ?array $connectDomains = null,
        public readonly ?array $resourceDomains = null,
        public readonly ?array $frameDomains = null,
        public readonly ?array $baseUriDomains = null,
        public readonly ?array $permissions = null,
        public readonly ?bool $prefersBorder = null,
        public readonly ?string $domain = null,
    ) {}

    public function toAppMeta(): AppMetaData
    {
        $meta = AppMetaData::make();

        if ($this->connectDomains !== null || $this->resourceDomains !== null || $this->frameDomains !== null || $this->baseUriDomains !== null) {
            $csp = Csp::make();

            if ($this->connectDomains !== null) {
                $csp->connectDomains($this->connectDomains);
            }

            if ($this->resourceDomains !== null) {
                $csp->resourceDomains($this->resourceDomains);
            }

            if ($this->frameDomains !== null) {
                $csp->frameDomains($this->frameDomains);
            }

            if ($this->baseUriDomains !== null) {
                $csp->baseUriDomains($this->baseUriDomains);
            }

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

        return $meta;
    }
}
