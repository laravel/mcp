<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\OAuth;

class Pkce
{
    public function __construct(
        public string $verifier,
        public string $challenge,
    ) {}

    public static function generate(): self
    {
        $verifier = self::base64Url(random_bytes(64));
        $challenge = self::base64Url(hash('sha256', $verifier, true));

        return new self($verifier, $challenge);
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
