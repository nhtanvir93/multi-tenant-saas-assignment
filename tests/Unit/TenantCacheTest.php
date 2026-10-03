<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Cache\TenantCache;
use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Tests\TestCase;

/**
 * @covers \App\Support\Cache\TenantCache
 */
final class TenantCacheTest extends TestCase
{
    /**
     * Verify tenant cache keys contain the tenant ID.
     */
    public function test_cache_keys_are_tenant_scoped(): void
    {
        $cache = new Repository(
            new ArrayStore,
        );

        $tenantContext = app(TenantContext::class);

        $tenantContext->runAs(
            10,
            function () use ($cache, $tenantContext): void {
                $tenantCache = new TenantCache(
                    $tenantContext,
                    $cache,
                );

                self::assertSame(
                    'tenant:10:dashboard',
                    $tenantCache->key('dashboard'),
                );
            },
        );
    }

    /**
     * Verify two tenants cannot share the same cache key.
     */
    public function test_cache_keys_are_separated_between_tenants(): void
    {
        $cache = new Repository(
            new ArrayStore,
        );

        $tenantContext = app(TenantContext::class);

        $tenantTenKey = $tenantContext->runAs(
            10,
            function () use ($cache, $tenantContext): string {
                $tenantCache = new TenantCache(
                    $tenantContext,
                    $cache,
                );

                return $tenantCache->key('dashboard');
            },
        );

        $tenantTwentyKey = $tenantContext->runAs(
            20,
            function () use ($cache, $tenantContext): string {
                $tenantCache = new TenantCache(
                    $tenantContext,
                    $cache,
                );

                return $tenantCache->key('dashboard');
            },
        );

        self::assertNotSame(
            $tenantTenKey,
            $tenantTwentyKey,
        );

        self::assertSame(
            'tenant:10:dashboard',
            $tenantTenKey,
        );

        self::assertSame(
            'tenant:20:dashboard',
            $tenantTwentyKey,
        );
    }

    /**
     * Verify customer list versions start at one.
     */
    public function test_customer_list_version_defaults_to_one(): void
    {
        $cache = new Repository(
            new ArrayStore,
        );

        $tenantContext = app(TenantContext::class);

        $tenantContext->runAs(
            10,
            function () use ($cache, $tenantContext): void {
                $tenantCache = new TenantCache(
                    $tenantContext,
                    $cache,
                );

                self::assertSame(
                    1,
                    $tenantCache->customersVersion(),
                );
            },
        );
    }

    /**
     * Verify customer list version can be bumped.
     */
    public function test_customer_list_version_can_be_bumped(): void
    {
        $cache = new Repository(
            new ArrayStore,
        );

        $tenantContext = app(TenantContext::class);

        $tenantContext->runAs(
            10,
            function () use ($cache, $tenantContext): void {
                $tenantCache = new TenantCache(
                    $tenantContext,
                    $cache,
                );

                $tenantCache->bumpCustomersVersion();

                self::assertSame(
                    2,
                    $tenantCache->customersVersion(),
                );
            },
        );
    }

    /**
     * Verify versioned customer keys contain the current version.
     */
    public function test_customer_list_key_contains_version_and_hash(): void
    {
        $cache = new Repository(
            new ArrayStore,
        );

        $tenantContext = app(TenantContext::class);

        $tenantContext->runAs(
            10,
            function () use ($cache, $tenantContext): void {
                $tenantCache = new TenantCache(
                    $tenantContext,
                    $cache,
                );

                $key = $tenantCache->customerListKey('abc123');

                self::assertSame(
                    'tenant:10:customers:v1:abc123',
                    $key,
                );
            },
        );
    }
}
