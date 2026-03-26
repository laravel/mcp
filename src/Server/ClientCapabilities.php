<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

class ClientCapabilities
{
    /**
     * @param  array<string, mixed>  $capabilities
     */
    public function __construct(protected array $capabilities = [])
    {
        //
    }

    public function supports(string $capability): bool
    {
        return array_key_exists($capability, $this->capabilities);
    }

    public function supportsExtension(string $extension): bool
    {
        $extensions = $this->capabilities['extensions'] ?? [];

        return is_array($extensions) && array_key_exists($extension, $extensions);
    }

    public function supportsUi(): bool
    {
        return $this->supportsExtension('io.modelcontextprotocol/ui');
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->capabilities;
    }
}
