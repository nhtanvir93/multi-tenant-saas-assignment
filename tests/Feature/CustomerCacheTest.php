<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\CustomerFilters;
use App\Enums\CustomerStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Support\Cache\TenantCache;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * @covers \App\Repositories\CachedCustomerRepository
 */
final class CustomerCacheTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify a cache miss builds the customer list and populates the cache.
     */
    public function test_customer_list_cache_miss_builds_and_populates_cache(): void
    {
        [$company, $owner] = $this->createTenant();

        Sanctum::actingAs($owner);

        $filters = new CustomerFilters(
            search: null,
            status: null,
            sort: '-created_at',
            perPage: 20,
            page: 1,
        );

        $this->runTenant(
            $company->id,
            function () use ($company, $filters): void {
                Customer::factory()->create([
                    'company_id' => $company->id,
                    'name' => 'Cache Miss Customer',
                    'status' => CustomerStatus::Active,
                ]);

                /** @var CustomerRepositoryInterface $repository */
                $repository = app(CustomerRepositoryInterface::class);

                /** @var TenantCache $tenantCache */
                $tenantCache = app(TenantCache::class);

                $queryHash = md5(
                    json_encode([
                        'search' => $filters->search,
                        'status' => $filters->status?->value,
                        'sort' => $filters->sort,
                        'per_page' => $filters->perPage,
                        'page' => $filters->page,
                    ], JSON_THROW_ON_ERROR),
                );

                $cacheKey = $tenantCache->customerListKey($queryHash);

                self::assertFalse(
                    $tenantCache->repository()->has($cacheKey),
                );

                $result = $repository->paginate($filters);

                self::assertSame(1, $result->total());

                self::assertTrue(
                    $tenantCache->repository()->has($cacheKey),
                );
            },
        );
    }

    /**
     * Verify a cached customer list avoids rebuilding the result.
     */
    public function test_customer_list_cache_hit_avoids_rebuilding_result(): void
    {
        [$company, $owner] = $this->createTenant();

        Sanctum::actingAs($owner);

        $filters = new CustomerFilters(
            search: null,
            status: null,
            sort: '-created_at',
            perPage: 20,
            page: 1,
        );

        $this->runTenant(
            $company->id,
            function () use ($company, $filters): void {
                Customer::factory()->create([
                    'company_id' => $company->id,
                    'name' => 'Original Customer',
                    'status' => CustomerStatus::Active,
                ]);

                /** @var CustomerRepositoryInterface $repository */
                $repository = app(CustomerRepositoryInterface::class);

                $firstResult = $repository->paginate($filters);

                self::assertSame(1, $firstResult->total());

                $firstCustomer = $firstResult->getCollection()->first();

                self::assertNotNull($firstCustomer);
                self::assertSame(
                    'Original Customer',
                    $firstCustomer->name,
                );

                /** @var Customer $customer */
                $customer = Customer::query()->firstOrFail();

                /*
                 * This test verifies cache-hit behaviour.
                 *
                 * Customer observers intentionally do not run here because
                 * their job is to invalidate the customer cache. That
                 * behaviour is tested separately below.
                 */
                Model::withoutEvents(
                    function () use ($customer): void {
                        $customer->update([
                            'name' => 'Changed After Cache',
                        ]);
                    },
                );

                $secondResult = $repository->paginate($filters);

                self::assertSame(1, $secondResult->total());

                $secondCustomer = $secondResult->getCollection()->first();

                self::assertNotNull($secondCustomer);

                self::assertSame(
                    'Original Customer',
                    $secondCustomer->name,
                );
            },
        );
    }

    /**
     * Verify customer-list version changes after a customer write.
     */
    public function test_customer_write_changes_customer_list_version(): void
    {
        [$company] = $this->createTenant();

        $this->runTenant(
            $company->id,
            function () use ($company): void {
                /** @var TenantCache $tenantCache */
                $tenantCache = app(TenantCache::class);

                $before = $tenantCache->customersVersion();

                Customer::factory()->create([
                    'company_id' => $company->id,
                    'status' => CustomerStatus::Active,
                ]);

                self::assertGreaterThan(
                    $before,
                    $tenantCache->customersVersion(),
                );
            },
        );
    }

    /**
     * Verify a customer write moves reads to a new versioned cache key.
     */
    public function test_customer_write_invalidates_previous_list_cache_version(): void
    {
        [$company, $owner] = $this->createTenant();

        Sanctum::actingAs($owner);

        $filters = new CustomerFilters(
            search: null,
            status: null,
            sort: '-created_at',
            perPage: 20,
            page: 1,
        );

        $this->runTenant(
            $company->id,
            function () use ($company, $filters): void {
                Customer::factory()->create([
                    'company_id' => $company->id,
                    'name' => 'Initial Customer',
                    'status' => CustomerStatus::Active,
                ]);

                /** @var CustomerRepositoryInterface $repository */
                $repository = app(CustomerRepositoryInterface::class);

                /** @var TenantCache $tenantCache */
                $tenantCache = app(TenantCache::class);

                /*
                 * Populate the first version of the customer-list cache.
                 */
                $repository->paginate($filters);

                $versionBefore = $tenantCache->customersVersion();

                $queryHash = md5(
                    json_encode([
                        'search' => $filters->search,
                        'status' => $filters->status?->value,
                        'sort' => $filters->sort,
                        'per_page' => $filters->perPage,
                        'page' => $filters->page,
                    ], JSON_THROW_ON_ERROR),
                );

                $oldCacheKey = $tenantCache->customerListKey($queryHash);

                self::assertTrue(
                    $tenantCache->repository()->has($oldCacheKey),
                );

                /*
                 * A customer write must bump the version.
                 */
                Customer::factory()->create([
                    'company_id' => $company->id,
                    'name' => 'New Customer',
                    'status' => CustomerStatus::Active,
                ]);

                $versionAfter = $tenantCache->customersVersion();

                self::assertGreaterThan(
                    $versionBefore,
                    $versionAfter,
                );

                $newCacheKey = $tenantCache->customerListKey($queryHash);

                self::assertNotSame(
                    $oldCacheKey,
                    $newCacheKey,
                );
            },
        );
    }

    /**
     * Verify customer cache keys include the tenant and query hash.
     */
    public function test_customer_list_cache_key_is_tenant_scoped(): void
    {
        [$company] = $this->createTenant();

        $this->runTenant(
            $company->id,
            function () use ($company): void {
                /** @var TenantCache $tenantCache */
                $tenantCache = app(TenantCache::class);

                $key = $tenantCache->customerListKey(
                    md5('test-query'),
                );

                self::assertStringStartsWith(
                    sprintf(
                        'tenant:%d:customers:v',
                        $company->id,
                    ),
                    $key,
                );
            },
        );
    }

    /**
     * Create a free-plan tenant.
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
