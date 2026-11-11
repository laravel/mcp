<?php

declare(strict_types=1);

namespace Laravel\Mcp\Support;

use Closure;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;

/**
 * @internal
 */
class AddressGuard
{
    public const NON_PUBLIC_IPV6_PREFIXES = [
        'ff',
        '2002',
        '20010000',
        '0064ff9b0001',
        'fec',
        'fed',
        'fee',
        'fef',
        '000000000000000000000000',
        '0000000000000000ffff0000',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    protected array $resolved = [];

    protected static ?Closure $hostResolver = null;

    public function __construct(protected ?Closure $lookupUsing = null) {}

    public static function resolveHostsUsing(?Closure $callback): void
    {
        static::$hostResolver = $callback;
    }

    public function isPublicHost(string $host, bool $allowLoopback = false): bool
    {
        $host = $this->normalizedHost($host);

        if ($host === '') {
            return false;
        }

        $addresses = $this->isIpAddress($host) ? [$host] : $this->resolve($host);

        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if ($this->isPublicAddress($address)) {
                continue;
            }

            if ($allowLoopback && $this->isLoopbackAddress($address)) {
                continue;
            }

            return false;
        }

        return true;
    }

    public function isPublicAddress(string $address): bool
    {
        if (! $this->isIpAddress($address)) {
            return false;
        }

        $packed = (string) inet_pton($address);

        if (str_starts_with($packed, str_repeat("\0", 10)."\xFF\xFF") || str_starts_with($packed, "\x00\x64\xFF\x9B".str_repeat("\0", 8))) {
            return $this->isPublicAddress((string) inet_ntop(substr($packed, 12)));
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE) === false) {
            return false;
        }

        if (strlen($packed) === 4) {
            $long = (int) ip2long($address);

            return ($long & 0xFFC00000) !== 0x64400000 && ($long & 0xF0000000) !== 0xE0000000;
        }

        return ! Str::startsWith(bin2hex($packed), self::NON_PUBLIC_IPV6_PREFIXES);
    }

    public function isLoopbackAddress(string $address): bool
    {
        if (! $this->isIpAddress($address)) {
            return false;
        }

        $packed = (string) inet_pton($address);

        if (strlen($packed) === 4) {
            return $packed[0] === "\x7F";
        }

        return $packed === str_repeat("\0", 15)."\x01";
    }

    /**
     * @return array<int, string>
     */
    public function resolve(string $host): array
    {
        return $this->resolved[$host] ??= $this->lookupUsing instanceof Closure
            ? ($this->lookupUsing)($host)
            : $this->lookup($host);
    }

    public function pin(PendingRequest $request): PendingRequest
    {
        return $request->withMiddleware(fn (callable $handler): Closure => fn (RequestInterface $request, array $options): PromiseInterface => $handler($request, $this->pinned($request, $options)));
    }

    public function guard(PendingRequest $request, bool $allowLoopback = false): PendingRequest
    {
        return $request->withMiddleware(fn (callable $handler): Closure => function (RequestInterface $request, array $options) use ($handler, $allowLoopback): PromiseInterface {
            if (! defined('CURLOPT_RESOLVE') || ! $this->isPublicHost($request->getUri()->getHost(), $allowLoopback)) {
                throw new ConnectException("Refusing to fetch [{$request->getUri()}] from a special-use or unresolvable address.", $request);
            }

            return $handler($request, $this->pinned($request, $options));
        });
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function pinned(RequestInterface $request, array $options): array
    {
        $uri = $request->getUri();
        $addresses = $this->resolved[$this->normalizedHost($uri->getHost())] ?? [];

        if ($addresses !== [] && defined('CURLOPT_RESOLVE')) {
            $port = $uri->getPort() ?? ($uri->getScheme() === 'https' ? 443 : 80);
            $addresses = array_map(fn (string $address): string => str_contains($address, ':') ? "[{$address}]" : $address, $addresses);

            $options['curl'][CURLOPT_RESOLVE] = ["{$uri->getHost()}:{$port}:".implode(',', $addresses)];
        }

        return $options;
    }

    protected function isIpAddress(string $host): bool
    {
        return filter_var($host, FILTER_VALIDATE_IP) !== false;
    }

    protected function normalizedHost(string $host): string
    {
        return strtolower(trim($host, '[]'));
    }

    /**
     * @return array<int, string>
     */
    protected function lookup(string $host): array
    {
        if (static::$hostResolver instanceof Closure) {
            return (static::$hostResolver)($host);
        }

        $addresses = @gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            $addresses[] = $record['ipv6'];
        }

        return array_values(array_unique($addresses));
    }
}
