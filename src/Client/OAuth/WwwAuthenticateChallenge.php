<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\OAuth;

use Illuminate\Support\Arr;
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

        preg_match_all('/([\w-]+)\s*=\s*("[^"]*"|[^,\s]+)/', $header, $matches, PREG_SET_ORDER);

        $params = [];

        foreach ($matches as $match) {
            $params[strtolower($match[1])] = HeaderUtils::unquote($match[2]);
        }

        return new self(
            resourceMetadataUrl: Arr::get($params, 'resource_metadata'),
            error: Arr::get($params, 'error'),
            errorDescription: Arr::get($params, 'error_description'),
            scope: Arr::get($params, 'scope'),
        );
    }
}
