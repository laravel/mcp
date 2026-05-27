<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Auth;

class Pkce
{
    public function __construct(
        public readonly string $verifier,
        public readonly string $challenge,
        public readonly string $method = 'S256',
    ) {}

    public static function generate(): self
    {
        $verifier = self::base64UrlEncode(random_bytes(32));
        $challenge = self::base64UrlEncode(hash('sha256', $verifier, true));

        return new self($verifier, $challenge);
    }

    private static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
