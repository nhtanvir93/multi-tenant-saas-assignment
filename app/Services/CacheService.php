<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Cache\TenantCache;
use Closure;
use Illuminate\Contracts\Cache\Lock;

/**
 * Application-level cache service.
 *
 * Keeps cache policy out of controllers and domain services.
 */
final class CacheService
{
    public function __construct(
        private readonly TenantCache $tenantCache,
    ) {}

    /**
     * Get or rebuild the tenant subscription cache.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function subscription(Closure $callback): mixed
    {
        return $this->tenantCache->remember(
            'subscription',
            TenantCache::SUBSCRIPTION_TTL,
            $callback,
        );
    }

    /**
     * Get or rebuild the tenant usage cache.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function usage(Closure $callback): mixed
    {
        return $this->tenantCache->remember(
            'usage',
            TenantCache::USAGE_TTL,
            $callback,
        );
    }

    /**
     * Get or rebuild the tenant dashboard cache.
     *
     * @template T
     *
     * @param  string  $visibility  Cache visibility bucket.
     * @param  Closure(): T  $callback
     * @return T
     */
    public function dashboard(
        string $visibility,
        Closure $callback,
    ): mixed {
        return $this->tenantCache->repository()->remember(
            $this->tenantCache->dashboardKey($visibility),
            TenantCache::DASHBOARD_TTL,
            $callback,
        );
    }

    /**
     * Get or rebuild a cached customer list.
     *
     * @template T
     *
     * @param  string  $queryHash  Normalized query hash.
     * @param  Closure(): T  $callback  Cache rebuild callback.
     * @return T
     */
    public function customers(
        string $queryHash,
        Closure $callback,
    ): mixed {
        return $this->tenantCache->repository()->remember(
            $this->tenantCache->customerListKey($queryHash),
            TenantCache::CUSTOMERS_TTL,
            $callback,
        );
    }

    /**
     * Forget subscription cache.
     */
    public function forgetSubscription(): void
    {
        $this->tenantCache->forget('subscription');
    }

    /**
     * Forget usage cache.
     */
    public function forgetUsage(): void
    {
        $this->tenantCache->forget('usage');
    }

    /**
     * Forget all dashboard visibility caches.
     */
    public function forgetDashboard(): void
    {
        $this->tenantCache->repository()->forget(
            $this->tenantCache->dashboardKey('full'),
        );

        $this->tenantCache->repository()->forget(
            $this->tenantCache->dashboardKey('summary'),
        );
    }

    /**
     * Invalidate customer-list cache by bumping its version.
     */
    public function bumpCustomerListVersion(): void
    {
        $this->tenantCache->bumpCustomersVersion();
    }

    /**
     * Invalidate all cache entries affected by a user write.
     */
    public function invalidateUserCaches(): void
    {
        $this->forgetUsage();
        $this->forgetDashboard();
    }

    /**
     * Invalidate all cache entries affected by a customer write.
     */
    public function invalidateCustomerCaches(): void
    {
        $this->forgetUsage();
        $this->forgetDashboard();
        $this->bumpCustomerListVersion();
    }

    /**
     * Invalidate all cache entries affected by a subscription/plan change.
     */
    public function invalidateSubscriptionCaches(): void
    {
        $this->forgetSubscription();
        $this->forgetUsage();
        $this->forgetDashboard();
    }

    /**
     * Create a lock used to prevent concurrent dashboard rebuilds.
     */
    public function dashboardLock(int $seconds = 10): Lock
    {
        return $this->tenantCache->dashboardLock($seconds);
    }
}
