<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Schema;

use Illuminate\Support\Arr;
use Laravel\Mcp\Exceptions\ClientException;
use Stringable;

class ResourceReadResult implements Stringable
{
    /**
     * @param  array<int, array<string, mixed>>  $contents
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public readonly array $contents,
        public readonly ?array $meta = null,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public static function from(array $result): self
    {
        $contents = Arr::get($result, 'contents', []);
        $meta = Arr::get($result, '_meta');

        if (! is_array($contents)) {
            throw new ClientException('Invalid resources/read result from server.');
        }

        return new self(
            contents: array_values(array_filter($contents, is_array(...))),
            meta: is_array($meta) ? $meta : null,
        );
    }

    public function mimeType(): ?string
    {
        foreach ($this->contents as $content) {
            $mimeType = Arr::get($content, 'mimeType');

            if (is_string($mimeType) && $mimeType !== '') {
                return $mimeType;
            }
        }

        return null;
    }

    public function content(): string
    {
        $parts = [];

        foreach ($this->contents as $content) {
            $text = Arr::get($content, 'text');

            if (is_string($text)) {
                $parts[] = $text;

                continue;
            }

            $blob = Arr::get($content, 'blob');

            if (is_string($blob)) {
                $decoded = base64_decode($blob, true);

                if ($decoded !== false) {
                    $parts[] = $decoded;
                }
            }
        }

        return implode('', $parts);
    }

    public function __toString(): string
    {
        return $this->content();
    }
}
