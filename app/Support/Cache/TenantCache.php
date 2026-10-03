<?php

declare(strict_types=1);

namespace App\Support\Cache;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Builds and manages tenant-scoped cache keys.
 *
 * All tenant cache keys are prefixed with tenant:{company_id}.
 */
final class TenantCache
{
    /**
     * Cache TTL for the subscription payload.
     */
    public const SUBSCRIPTION_TTL = 3600;

    /**
     * Cache TTL for usage data.
     */
    public const USAGE_TTL = 600;

    /**
     * Cache TTL for dashboard data.
     */
    public const DASHBOARD_TTL = 300;

    /**
     * Cache TTL for customer list pages.
     */
    public const CUSTOMERS_TTL = 300;

    /**
     * Cache key used for customer-list versioning.
     */
    private const CUSTOMERS_VERSION_SUFFIX = 'customers:ver';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * Build a tenant-scoped cache key.
     *
     * @param  string  $name  Cache namespace/name.
     */
    public function key(string $name): string
    {
        return sprintf(
            'tenant:%d:%s',
            $this->tenantContext->id(),
            $name,
        );
    }

    /**
     * Retrieve a tenant-scoped cache value.
     */
    public function get(string $name): mixed
    {
        return $this->cache->get($this->key($name));
    }

    /**
     * Determine whether a tenant cache entry exists.
     */
    public function has(string $name): bool
    {
        return $this->cache->has($this->key($name));
    }

    /**
     * Store a value in the tenant cache.
     *
     * @param  string  $name  Cache namespace/name.
     * @param  mixed  $value  Value to cache.
     * @param  int  $seconds  TTL in seconds.
     */
    public function put(
        string $name,
        mixed $value,
        int $seconds,
    ): void {
        $this->cache->put(
            $this->key($name),
            $value,
            $seconds,
        );
    }

    /**
     * Forget a tenant cache entry.
     */
    public function forget(string $name): void
    {
        $this->cache->forget($this->key($name));
    }

    /**
     * Cache-aside lookup.
     *
     * @template T
     *
     * @param  string  $name  Cache namespace/name.
     * @param  int  $seconds  TTL in seconds.
     * @param  Closure(): T  $callback  Cache rebuild callback.
     * @return T
     */
    public function remember(
        string $name,
        int $seconds,
        Closure $callback,
    ): mixed {
        return $this->cache->remember(
            $this->key($name),
            $seconds,
            $callback,
        );
    }

    /**
     * Build the tenant customer-list version key.
     */
    public function customersVersionKey(): string
    {
        return $this->key(self::CUSTOMERS_VERSION_SUFFIX);
    }

    /**
     * Get the current customer-list cache version.
     */
    public function customersVersion(): int
    {
        return (int) $this->cache->get(
            $this->customersVersionKey(),
            1,
        );
    }

    /**
     * Bump the customer-list cache version.
     *
     * Versioning avoids expensive Redis wildcard/key scans.
     */
    public function bumpCustomersVersion(): int
    {
        $key = $this->customersVersionKey();

        if (! $this->cache->has($key)) {
            $this->cache->forever($key, 1);
        }

        return (int) $this->cache->increment($key);
    }

    /**
     * Build a versioned customer-list cache key.
     *
     * @param  string  $queryHash  Normalized query hash.
     */
    public function customerListKey(string $queryHash): string
    {
        return $this->key(sprintf(
            'customers:v%d:%s',
            $this->customersVersion(),
            $queryHash,
        ));
    }

    /**
     * Return the underlying cache repository.
     */
    public function repository(): CacheRepository
    {
        return $this->cache;
    }

    /**
     * Build a role-aware dashboard cache key.
     */
    public function dashboardKey(string $visibility): string
    {
        return $this->key(
            sprintf('dashboard:%s', $visibility),
        );
    }

    /**
     * Acquire the tenant-scoped dashboard rebuild lock.
     *
     * @param  int  $seconds  Lock TTL.
     */
    public function dashboardLock(int $seconds = 10): Lock
    {
        /** @var RedisStore $store */
        $store = $this->cache->getStore();

        return $store->lock(
            $this->key('dashboard:lock'),
            $seconds,
        );
    }
}
