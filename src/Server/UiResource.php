<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Laravel\Mcp\Server\Attributes\UiMeta as UiMetaAttribute;
use Laravel\Mcp\Server\Ui\UiMeta;

abstract class UiResource extends Resource
{
    protected string $mimeType = 'text/html;profile=mcp-app';

    protected string $defaultUriScheme = 'ui';

    public function uiMeta(): UiMeta
    {
        $attribute = $this->resolveAttribute(UiMetaAttribute::class);

        return $attribute?->toUiMeta() ?? new UiMeta;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedUiMeta(): array
    {
        $uiMeta = $this->uiMeta()->toArray();

        if (! isset($uiMeta['domain'])) {
            $domain = parse_url((string) config('app.url', ''), PHP_URL_HOST) ?: null;

            if ($domain !== null) {
                $uiMeta['domain'] = $domain;
            }
        }

        return $uiMeta;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        $uiMeta = $this->resolvedUiMeta();

        if ($uiMeta !== []) {
            $data['_meta'] = array_merge($data['_meta'] ?? [], ['ui' => $uiMeta]);
        }

        return $data;
    }
}
