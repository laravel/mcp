<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\OAuth;

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

        preg_match_all('/(\w+)="([^"]*)"/', $header, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $params[$match[1]] = $match[2];
        }

        return new self(
            resourceMetadataUrl: $params['resource_metadata'] ?? null,
            error: $params['error'] ?? null,
            errorDescription: $params['error_description'] ?? null,
            scope: $params['scope'] ?? null,
        );
    }
}
