<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

class WwwAuthenticateChallenge
{
    public function __construct(
        public ?string $realm,
        public ?string $scope,
        public ?string $resourceMetadata,
        public ?string $error,
        public ?string $errorDescription,
    ) {}

    public static function parse(?string $header): ?self
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        if (! preg_match('/^Bearer\s*(.*)$/i', trim($header), $bearerMatch)) {
            return null;
        }

        $params = $bearerMatch[1];

        return new self(
            realm: self::extract($params, 'realm'),
            scope: self::extract($params, 'scope'),
            resourceMetadata: self::extract($params, 'resource_metadata'),
            error: self::extract($params, 'error'),
            errorDescription: self::extract($params, 'error_description'),
        );
    }

    public function isInsufficientScope(): bool
    {
        return $this->error === 'insufficient_scope';
    }

    protected static function extract(string $params, string $name): ?string
    {
        $pattern = '/'.preg_quote($name, '/').'\s*=\s*(?:"([^"]*)"|([^\s,]+))/i';

        if (! preg_match($pattern, $params, $match)) {
            return null;
        }

        $value = $match[1] !== '' ? $match[1] : ($match[2] ?? '');

        return $value === '' ? null : $value;
    }
}
