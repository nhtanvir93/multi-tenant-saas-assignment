<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TenantResourceChanged;
use App\Services\CacheService;
use App\Support\Tenancy\TenantContext;

/**
 * Invalidates tenant cache after a committed database change.
 */
final class InvalidateTenantCache
{
    public function __construct(
        private readonly CacheService $cacheService,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * Handle the resource-changed event.
     */
    public function handle(TenantResourceChanged $event): void
    {
        $this->tenantContext->runAs(
            $event->companyId,
            function () use ($event): void {
                match ($event->resource) {
                    'users' => $this->cacheService->invalidateUserCaches(),
                    'customers' => $this->cacheService->invalidateCustomerCaches(),
                    'subscription' => $this->cacheService->invalidateSubscriptionCaches(),
                    default => null,
                };
            },
        );
    }
}
