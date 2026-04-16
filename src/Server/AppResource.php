<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Laravel\Mcp\Server\Attributes\AppMeta as AppMetaAttribute;
use Laravel\Mcp\Server\Ui\AppMeta;
use Laravel\Mcp\Server\Ui\Enums\Library;

abstract class AppResource extends Resource
{
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
            $domain = parse_url((string) config('app.url', ''), PHP_URL_HOST) ?: null;

            if ($domain !== null) {
                $appMeta['domain'] = $domain;
            }
        }

        return $appMeta;
    }

    public function libraryScripts(): string
    {
        $libraries = $this->appMeta()->getLibraries();

        if ($libraries === []) {
            return '';
        }

        return implode("\n", array_map(
            fn(Library $lib): string => implode("\n", $lib->scriptTags()),
            $libraries,
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
}
