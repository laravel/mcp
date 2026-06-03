<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\OAuth;

use Symfony\Component\HttpFoundation\HeaderUtils;

class WwwAuthenticateChallenge
{
    public function __construct(
        public ?string $resourceMetadataUrl = null,
        public ?string $error = null,
        public ?string $errorDescription = null,
        public ?string $scope = null,
    ) {}

    public static function parse(?string $header): self
    {
        if ($header === null || $header === '') {
            return new self;
        }

        $params = [];

        foreach (HeaderUtils::split($header, ',') as $part) {
            $part = trim((string) $part);

            if ($part === '') {
                continue;
            }

            if (str_contains($part, ' ')) {
                [$scheme, $value] = explode(' ', $part, 2);

                if (! str_contains($scheme, '=')) {
                    $part = trim($value);
                }
            }

            if (! str_contains($part, '=')) {
                continue;
            }

            $pair = HeaderUtils::split($part, '=');

            if (count($pair) !== 2) {
                continue;
            }

            $key = strtolower(trim((string) $pair[0]));

            if (str_contains($key, ' ')) {
                $key = substr($key, strrpos($key, ' ') + 1);
            }

            $params[$key] = HeaderUtils::unquote(trim((string) $pair[1]));
        }

        return new self(
            resourceMetadataUrl: $params['resource_metadata'] ?? null,
            error: $params['error'] ?? null,
            errorDescription: $params['error_description'] ?? null,
            scope: $params['scope'] ?? null,
        );
    }
}
