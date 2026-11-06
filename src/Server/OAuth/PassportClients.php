<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\OAuth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Laravel\Mcp\Server\Registrar;

class PassportClients
{
    /**
     * Add the MCP scope to a scope-restricted Passport client.
     */
    public function grantMcpScope(mixed $client): void
    {
        if (! $client instanceof Model) {
            return;
        }

        $scopes = $client->refresh()->getAttribute('scopes');

        if (! is_array($scopes) || in_array(Registrar::OAUTH_SCOPE, $scopes, true)) {
            return;
        }

        $client->forceFill(['scopes' => [...$scopes, Registrar::OAUTH_SCOPE]])->save();
    }

    /**
     * Persist the client metadata the Passport client table has columns for.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function persistMetadata(mixed $client, array $metadata): array
    {
        if (! $client instanceof Model) {
            return [];
        }

        $columns = $client->getConnection()->getSchemaBuilder()->getColumnListing($client->getTable());
        $supported = array_intersect(['logo_uri', 'client_uri'], $columns);
        $values = array_filter(Arr::only($metadata, $supported));

        if ($values !== []) {
            $client->forceFill($values)->save();
        }

        return array_filter($client->only($supported));
    }
}
