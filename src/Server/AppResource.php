<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Laravel\Mcp\Server\Attributes\AppMeta as AppMetaAttribute;
use Laravel\Mcp\Server\Ui\AppMeta;

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
