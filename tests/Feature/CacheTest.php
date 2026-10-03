<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Events\TenantResourceChanged;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Cache\TenantCache;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * @covers \App\Services\CacheService
 * @covers \App\Support\Cache\TenantCache
 * @covers \App\Observers\UserObserver
 * @covers \App\Observers\CustomerObserver
 * @covers \App\Observers\SubscriptionObserver
 */
final class CacheTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify the first dashboard request populates the cache.
     */
    public function test_dashboard_cache_miss_builds_and_caches_payload(): void
    {
        [$company, $owner] = $this->createTenant();

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.users.used', 1)
            ->assertJsonPath('data.customers.used', 0);

        $this->withTenantCache(
            $company->id,
            function (TenantCache $tenantCache): void {
                self::assertTrue(
                    $tenantCache->repository()->has(
                        $tenantCache->dashboardKey('full'),
                    ),
                );
            },
        );

        self::assertSame(
            $company->id,
            $owner->company_id,
        );
    }

    /**
     * Verify repeated dashboard requests use the same cached payload.
     */
    public function test_dashboard_cache_hit_returns_cached_payload(): void
    {
        [$company, $owner] = $this->createTenant();

        Sanctum::actingAs($owner);

        $first = $this->getJson('/api/v1/dashboard');

        $first
            ->assertOk()
            ->assertJsonPath('data.users.used', 1);

        $this->withTenantCache(
            $company->id,
            function (TenantCache $tenantCache): void {
                $tenantCache->repository()->put(
                    $tenantCache->dashboardKey('full'),
                    [
                        'users' => [
                            'used' => 999,
                            'limit' => 5,
                            'percent' => 100,
                        ],
                        'customers' => [
                            'used' => 999,
                            'limit' => 100,
                            'percent' => 100,
                        ],
                        'plan' => [
                            'slug' => 'free',
                            'name' => 'Free',
                        ],
                    ],
                    300,
                );
            },
        );

        $second = $this->getJson('/api/v1/dashboard');

        $second
            ->assertOk()
            ->assertJsonPath('data.users.used', 999);
    }

    /**
     * Verify creating a user invalidates usage and dashboard caches.
     */
    public function test_user_create_invalidates_usage_and_dashboard_cache(): void
    {
        [$company, $owner] = $this->createTenant();

        Sanctum::actingAs($owner);

        $this->withTenantCache(
            $company->id,
            function (TenantCache $tenantCache): void {
                $tenantCache->repository()->put(
                    $tenantCache->key('usage'),
                    ['users' => ['used' => 1]],
                    600,
                );

                $tenantCache->repository()->put(
                    $tenantCache->dashboardKey('full'),
                    ['users' => ['used' => 1]],
                    300,
                );

                $this->postJson('/api/v1/users', [
                    'name' => 'Admin',
                    'email' => 'admin@example.com',
                    'password' => 'password123',
                    'role' => 'admin',
                    'status' => 'active',
                ])->assertCreated();

                self::assertFalse(
                    $tenantCache->repository()->has(
                        $tenantCache->key('usage'),
                    ),
                );

                self::assertFalse(
                    $tenantCache->repository()->has(
                        $tenantCache->dashboardKey('full'),
                    ),
                );
            },
        );
    }

    /**
     * Verify updating a user invalidates usage and dashboard caches.
     */
    public function test_user_update_invalidates_usage_and_dashboard_cache(): void
    {
        [$company, $owner] = $this->createTenant();

        $user = $this->runTenant(
            $company->id,
            fn (): User => User::factory()->create([
                'company_id' => $company->id,
                'role' => Role::User,
                'status' => UserStatus::Active,
            ]),
        );

        Sanctum::actingAs($owner);

        $this->withTenantCache(
            $company->id,
            function (TenantCache $tenantCache) use ($user): void {
                $tenantCache->repository()->put(
                    $tenantCache->key('usage'),
                    ['users' => ['used' => 2]],
                    600,
                );

                $tenantCache->repository()->put(
                    $tenantCache->dashboardKey('full'),
                    ['users' => ['used' => 2]],
                    300,
                );

                $this->putJson(
                    "/api/v1/users/{$user->id}",
                    [
                        'name' => 'Updated User',
                        'role' => 'user',
                        'status' => 'active',
                    ],
                )->assertOk();

                self::assertFalse(
                    $tenantCache->repository()->has(
                        $tenantCache->key('usage'),
                    ),
                );

                self::assertFalse(
                    $tenantCache->repository()->has(
                        $tenantCache->dashboardKey('full'),
                    ),
                );
            },
        );
    }

    /**
     * Verify deleting a user invalidates usage and dashboard caches.
     */
    public function test_user_delete_invalidates_usage_and_dashboard_cache(): void
    {
        [$company, $owner] = $this->createTenant();

        $user = $this->runTenant(
            $company->id,
            fn (): User => User::factory()->create([
                'company_id' => $company->id,
                'role' => Role::User,
                'status' => UserStatus::Active,
            ]),
        );

        Sanctum::actingAs($owner);

        $this->withTenantCache(
            $company->id,
            function (TenantCache $tenantCache) use ($user): void {
                $tenantCache->repository()->put(
                    $tenantCache->key('usage'),
                    ['users' => ['used' => 2]],
                    600,
                );

                $tenantCache->repository()->put(
                    $tenantCache->dashboardKey('full'),
                    ['users' => ['used' => 2]],
                    300,
                );

                $this->deleteJson(
                    "/api/v1/users/{$user->id}",
                )->assertOk();

                self::assertFalse(
                    $tenantCache->repository()->has(
                        $tenantCache->key('usage'),
                    ),
                );

                self::assertFalse(
                    $tenantCache->repository()->has(
                        $tenantCache->dashboardKey('full'),
                    ),
                );
            },
        );
    }

    /**
     * Verify creating a customer invalidates usage, dashboard and customer list cache.
     */
    public function test_customer_create_invalidates_customer_related_caches(): void
    {
        [$company, $owner] = $this->createTenant();

        Sanctum::actingAs($owner);

        $this->withTenantCache(
            $company->id,
            function (TenantCache $tenantCache): void {
                $tenantCache->repository()->put(
                    $tenantCache->key('usage'),
                    ['customers' => ['used' => 0]],
                    600,
                );

                $tenantCache->repository()->put(
                    $tenantCache->dashboardKey('full'),
                    ['customers' => ['used' => 0]],
                    300,
                );

                $oldVersion = $tenantCache->customersVersion();

                $this->postJson('/api/v1/customers', [
                    'name' => 'Acme',
                    'email' => 'acme@example.com',
                    'phone' => '01700000000',
                    'status' => 'active',
                    'notes' => 'Test customer',
                ])->assertCreated();

                self::assertFalse(
                    $tenantCache->repository()->has(
                        $tenantCache->key('usage'),
                    ),
                );

                self::assertFalse(
                    $tenantCache->repository()->has(
                        $tenantCache->dashboardKey('full'),
                    ),
                );

                self::assertGreaterThan(
                    $oldVersion,
                    $tenantCache->customersVersion(),
                );
            },
        );
    }

    /**
     * Verify updating a customer invalidates customer-related caches.
     */
    public function test_customer_update_invalidates_customer_related_caches(): void
    {
        [$company, $owner] = $this->createTenant();

        $customer = $this->runTenant(
            $company->id,
            fn (): Customer => Customer::factory()->create([
                'company_id' => $company->id,
                'status' => CustomerStatus::Active,
            ]),
        );

        Sanctum::actingAs($owner);

        $this->withTenantCache(
            $company->id,
            function (TenantCache $tenantCache) use ($customer): void {
                $tenantCache->repository()->put(
                    $tenantCache->key('usage'),
                    ['customers' => ['used' => 1]],
                    600,
                );

                $tenantCache->repository()->put(
                    $tenantCache->dashboardKey('full'),
                    ['customers' => ['used' => 1]],
                    300,
                );

                $oldVersion = $tenantCache->customersVersion();

                $this->putJson(
                    "/api/v1/customers/{$customer->id}",
                    [
                        'name' => 'Updated Customer',
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                        'status' => 'lead',
                        'notes' => $customer->notes,
                    ],
                )->assertOk();

                self::assertFalse(
                    $tenantCache->repository()->has(
                        $tenantCache->key('usage'),
                    ),
                );

                self::assertFalse(
                    $tenantCache->repository()->has(
                        $tenantCache->dashboardKey('full'),
                    ),
                );

                self::assertGreaterThan(
                    $oldVersion,
                    $tenantCache->customersVersion(),
                );
            },
        );
    }

    /**
     * Verify deleting a customer invalidates customer-related caches.
     */
    public function test_customer_delete_invalidates_customer_related_caches(): void
    {
        [$company, $owner] = $this->createTenant();

        $customer = $this->runTenant(
            $company->id,
            fn (): Customer => Customer::factory()->create([
                'company_id' => $company->id,
                'status' => CustomerStatus::Active,
            ]),
        );

        Sanctum::actingAs($owner);

        $this->withTenantCache(
            $company->id,
            function (TenantCache $tenantCache) use ($customer): void {
                $tenantCache->repository()->put(
                    $tenantCache->key('usage'),
                    ['customers' => ['used' => 1]],
                    600,
                );

                $tenantCache->repository()->put(
                    $tenantCache->dashboardKey('full'),
                    ['customers' => ['used' => 1]],
                    300,
                );

                $oldVersion = $tenantCache->customersVersion();

                $this->deleteJson(
                    "/api/v1/customers/{$customer->id}",
                )->assertOk();

                self::assertFalse(
                    $tenantCache->repository()->has(
                        $tenantCache->key('usage'),
                    ),
                );

                self::assertFalse(
                    $tenantCache->repository()->has(
                        $tenantCache->dashboardKey('full'),
                    ),
                );

                self::assertGreaterThan(
                    $oldVersion,
                    $tenantCache->customersVersion(),
                );
            },
        );
    }

    /**
     * Verify subscription changes invalidate subscription, usage and dashboard caches.
     */
    public function test_subscription_change_invalidates_subscription_related_caches(): void
    {
        [$company, $owner] = $this->createTenant();

        Sanctum::actingAs($owner);

        $this->withTenantCache(
            $company->id,
            function (TenantCache $tenantCache): void {
                $tenantCache->repository()->put(
                    $tenantCache->key('subscription'),
                    ['plan' => ['slug' => 'free']],
                    3600,
                );

                $tenantCache->repository()->put(
                    $tenantCache->key('usage'),
                    ['users' => ['used' => 1]],
                    600,
                );

                $tenantCache->repository()->put(
                    $tenantCache->dashboardKey('full'),
                    ['plan' => ['slug' => 'free']],
                    300,
                );

                /** @var Plan $pro */
                $pro = Plan::query()->firstOrCreate(
                    ['slug' => 'pro'],
                    [
                        'name' => 'Pro',
                        'tier' => 2,
                        'price_cents' => 0,
                        'max_users' => 50,
                        'max_customers' => 5000,
                        'features' => [],
                        'is_active' => true,
                    ],
                );

                $this->putJson('/api/v1/subscription', [
                    'plan_id' => $pro->id,
                ])->assertOk();

                self::assertFalse(
                    $tenantCache->repository()->has(
                        $tenantCache->key('subscription'),
                    ),
                );

                self::assertFalse(
                    $tenantCache->repository()->has(
                        $tenantCache->key('usage'),
                    ),
                );

                self::assertFalse(
                    $tenantCache->repository()->has(
                        $tenantCache->dashboardKey('full'),
                    ),
                );
            },
        );
    }

    /**
     * Verify one tenant cannot invalidate another tenant's cache.
     */
    public function test_tenant_cache_entries_are_isolated(): void
    {
        [$companyA, $ownerA] = $this->createTenant();
        [$companyB] = $this->createTenant();

        $this->withTenantCache(
            $companyA->id,
            function (TenantCache $cacheA) use ($companyB, $ownerA): void {
                $cacheA->repository()->put(
                    $cacheA->key('usage'),
                    ['tenant' => 'A'],
                    600,
                );

                $this->withTenantCache(
                    $companyB->id,
                    function (TenantCache $cacheB): void {
                        $cacheB->repository()->put(
                            $cacheB->key('usage'),
                            ['tenant' => 'B'],
                            600,
                        );

                        self::assertSame(
                            ['tenant' => 'B'],
                            $cacheB->repository()->get(
                                $cacheB->key('usage'),
                            ),
                        );
                    },
                );

                self::assertSame(
                    ['tenant' => 'A'],
                    $cacheA->repository()->get(
                        $cacheA->key('usage'),
                    ),
                );

                Sanctum::actingAs($ownerA);

                $this->postJson('/api/v1/users', [
                    'name' => 'Tenant A User',
                    'email' => 'tenant-a-user@example.com',
                    'password' => 'password123',
                    'role' => 'user',
                    'status' => 'active',
                ])->assertCreated();

                self::assertFalse(
                    $cacheA->repository()->has(
                        $cacheA->key('usage'),
                    ),
                );

                $this->withTenantCache(
                    $companyB->id,
                    function (TenantCache $cacheB): void {
                        self::assertTrue(
                            $cacheB->repository()->has(
                                $cacheB->key('usage'),
                            ),
                        );
                    },
                );
            },
        );
    }

    /**
     * Verify resource change events are dispatched after a committed write.
     */
    public function test_resource_change_event_is_dispatched_after_commit(): void
    {
        Event::fake([
            TenantResourceChanged::class,
        ]);

        [$company] = $this->createTenant();

        $this->runTenant(
            $company->id,
            function () use ($company): void {
                User::factory()->create([
                    'company_id' => $company->id,
                    'role' => Role::User,
                    'status' => UserStatus::Active,
                ]);
            },
        );

        Event::assertDispatched(
            TenantResourceChanged::class,
            function (TenantResourceChanged $event) use ($company): bool {
                return $event->companyId === $company->id
                    && $event->resource === 'users';
            },
        );
    }

    /**
     * Create a test tenant with a free subscription.
     *
     * @return array{0: Company, 1: User}
     */
    private function createTenant(): array
    {
        /** @var Plan $plan */
        $plan = Plan::query()->firstOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free',
                'tier' => 1,
                'price_cents' => 0,
                'max_users' => 5,
                'max_customers' => 100,
                'features' => [],
                'is_active' => true,
            ],
        );

        /** @var Company $company */
        $company = Company::factory()->create();

        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now(),
        ]);

        /** @var User $owner */
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::Owner,
            'status' => UserStatus::Active,
        ]);

        return [$company, $owner];
    }

    /**
     * Execute a callback with a tenant-scoped cache instance.
     *
     * @template T
     *
     * @param  Closure(TenantCache): T  $callback
     * @return T
     */
    private function withTenantCache(
        int $companyId,
        Closure $callback,
    ): mixed {
        return $this->runTenant(
            $companyId,
            function () use ($callback): mixed {
                return $callback(app(TenantCache::class));
            },
        );
    }

    /**
     * Execute a callback inside a tenant context.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function runTenant(
        int $companyId,
        Closure $callback,
    ): mixed {
        return app(TenantContext::class)->runAs(
            $companyId,
            $callback,
        );
    }
}
