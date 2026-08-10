<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server;

use Laravel\Mcp\Server;

class Subscription
{
    public const NOTIFICATIONS = [
        'toolsListChanged' => 'notifications/tools/list_changed',
        'promptsListChanged' => 'notifications/prompts/list_changed',
        'resourcesListChanged' => 'notifications/resources/list_changed',
        'resourceSubscriptions' => 'notifications/resources/updated',
    ];

    protected const CAPABILITIES = [
        'toolsListChanged' => Server::CAPABILITY_TOOLS.'.listChanged',
        'promptsListChanged' => Server::CAPABILITY_PROMPTS.'.listChanged',
        'resourcesListChanged' => Server::CAPABILITY_RESOURCES.'.listChanged',
        'resourceSubscriptions' => Server::CAPABILITY_RESOURCES.'.subscribe',
    ];

    /**
     * @param  array<string, bool|array<int, string>>  $notifications
     */
    protected function __construct(
        public readonly int|string $id,
        protected array $notifications,
    ) {
        //
    }

    public static function from(int|string $id, mixed $requested, ServerContext $context): self
    {
        $requested = is_array($requested) ? $requested : [];
        $honored = [];

        foreach (self::NOTIFICATIONS as $field => $method) {
            if (! $context->hasCapability(self::CAPABILITIES[$field])) {
                continue;
            }

            $value = $requested[$field] ?? null;

            if ($field === 'resourceSubscriptions') {
                $uris = is_array($value) ? array_values(array_filter($value, is_string(...))) : [];

                if ($uris !== []) {
                    $honored[$field] = $uris;
                }

                continue;
            }

            if ($value === true) {
                $honored[$field] = true;
            }
        }

        return new self($id, $honored);
    }

    /**
     * @return array<string, bool|array<int, string>>
     */
    public function notifications(): array
    {
        return $this->notifications;
    }

    public function wants(string $method, ?string $uri = null): bool
    {
        $field = array_search($method, self::NOTIFICATIONS, true);

        if ($field === false || ! array_key_exists($field, $this->notifications)) {
            return false;
        }

        if ($field !== 'resourceSubscriptions') {
            return true;
        }

        /** @var array<int, string> $uris */
        $uris = $this->notifications[$field];

        return $uri !== null && in_array($uri, $uris, true);
    }
}
