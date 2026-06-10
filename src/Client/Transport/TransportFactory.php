<?php

declare(strict_types=1);

namespace Laravel\Mcp\Client\Transport;

use Illuminate\Support\Arr;
use Laravel\Mcp\Client\Contracts\Transport;
use Laravel\Mcp\Exceptions\ClientException;

class TransportFactory
{
    /**
     * @param  array<string, mixed>  $recipe
     */
    public static function fromRecipe(array $recipe): Transport
    {
        return match (Arr::get($recipe, 'driver')) {
            'stdio' => self::stdio($recipe),
            'http' => self::http($recipe),
            default => throw new ClientException('Unable to rebuild transport from an unknown recipe.'),
        };
    }

    /**
     * @param  array<string, mixed>  $recipe
     */
    protected static function stdio(array $recipe): StdioTransport
    {
        $command = Arr::get($recipe, 'command');
        $args = Arr::get($recipe, 'args', []);

        if (! is_string($command) || ! is_array($args)) {
            throw new ClientException('Invalid stdio transport recipe.');
        }

        $transport = new StdioTransport($command, array_values($args));

        self::applyTimeout($transport, $recipe);

        return $transport;
    }

    /**
     * @param  array<string, mixed>  $recipe
     */
    protected static function http(array $recipe): HttpTransport
    {
        $url = Arr::get($recipe, 'url');

        if (! is_string($url)) {
            throw new ClientException('Invalid http transport recipe.');
        }

        $transport = new HttpTransport($url);

        $token = Arr::get($recipe, 'token');

        if (is_string($token)) {
            $transport->withToken($token);
        }

        $headers = Arr::get($recipe, 'headers');

        if (is_array($headers)) {
            /** @var array<string, string> $headers */
            $transport->withHeaders($headers);
        }

        self::applyTimeout($transport, $recipe);

        return $transport;
    }

    /**
     * @param  array<string, mixed>  $recipe
     */
    protected static function applyTimeout(Transport $transport, array $recipe): void
    {
        $timeout = Arr::get($recipe, 'timeoutSeconds');

        if (is_int($timeout) || is_float($timeout)) {
            $transport->setTimeoutSeconds((float) $timeout);
        }
    }
}
