<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Laravel\Mcp\Server\Attributes\AppMeta as AppMetaAttribute;
use Laravel\Mcp\Server\Ui\AppMeta;
use Laravel\Mcp\Server\Ui\Enums\Library;

abstract class AppResource extends Resource
{
    public const CLAUDE_DOMAIN_SUFFIX = '.claudemcpcontent.com';

    protected string $mimeType = 'text/html;profile=mcp-app';

    protected string $defaultUriScheme = 'ui';

    public function appMeta(): AppMeta
    {
        $attribute = $this->resolveAttribute(AppMetaAttribute::class);

        return $attribute?->toAppMeta() ?? new AppMeta;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedAppMeta(): array
    {
        $appMeta = $this->appMeta()->toArray();

        if (! isset($appMeta['domain'])) {
            $appMeta['domain'] = $this->toClaudeDomain(url()->current());
        }

        return $appMeta;
    }

    public function libraryScripts(): string
    {
        return implode("\n", array_map(
            fn (Library $lib): string => implode("\n", $lib->scriptTags()),
            $this->appMeta()->getLibraries(),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        $appMeta = $this->resolvedAppMeta();

        if ($appMeta !== []) {
            $data['_meta'] = array_merge($data['_meta'] ?? [], ['ui' => $appMeta]);
        }

        return $data;
    }

    private function toClaudeDomain(string $serverRoute): string
    {
        return str(hash('sha256', $serverRoute))
            ->limit(32, '')
            ->append(self::CLAUDE_DOMAIN_SUFFIX)
            ->value();
    }
}
