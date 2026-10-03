<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * @covers \App\Http\Controllers\Api\V1\DashboardController
 * @covers \App\Services\DashboardService
 */
final class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify the owner receives the complete dashboard payload.
     */
    public function test_owner_can_view_full_dashboard(): void
    {
        [$company, $owner] = $this->createDashboardTenant('free');

        User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::Admin,
            'status' => UserStatus::Active,
        ]);

        User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::User,
            'status' => UserStatus::Active,
        ]);

        $this->runDashboardTenant(
            $company->id,
            function () use ($company): void {
                Customer::factory()->create([
                    'company_id' => $company->id,
                    'status' => CustomerStatus::Active,
                ]);

                Customer::factory()->create([
                    'company_id' => $company->id,
                    'status' => CustomerStatus::Inactive,
                ]);

                Customer::factory()->create([
                    'company_id' => $company->id,
                    'status' => CustomerStatus::Lead,
                ]);
            },
        );

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.users.used', 3)
            ->assertJsonPath('data.users.limit', 5)
            ->assertJsonPath('data.users.percent', 60)
            ->assertJsonPath('data.customers.used', 3)
            ->assertJsonPath('data.customers.limit', 100)
            ->assertJsonPath('data.customers.percent', 3)
            ->assertJsonPath('data.plan.slug', 'free')
            ->assertJsonPath('data.plan.name', 'Free')
            ->assertJsonPath('data.customer_status.active', 1)
            ->assertJsonPath('data.customer_status.inactive', 1)
            ->assertJsonPath('data.customer_status.lead', 1)
            ->assertJsonPath('data.users_by_role.owner', 1)
            ->assertJsonPath('data.users_by_role.admin', 1)
            ->assertJsonPath('data.users_by_role.user', 1);
    }

    /**
     * Verify an admin receives the complete dashboard payload.
     */
    public function test_admin_can_view_full_dashboard(): void
    {
        [$company] = $this->createDashboardTenant('free');

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::Admin,
            'status' => UserStatus::Active,
        ]);

        $this->runDashboardTenant(
            $company->id,
            function () use ($company): void {
                Customer::factory()->count(2)->create([
                    'company_id' => $company->id,
                    'status' => CustomerStatus::Active,
                ]);
            },
        );

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.users.used', 2)
            ->assertJsonPath('data.users.limit', 5)
            ->assertJsonPath('data.users.percent', 40)
            ->assertJsonPath('data.customers.used', 2)
            ->assertJsonPath('data.customers.limit', 100)
            ->assertJsonPath('data.customers.percent', 2)
            ->assertJsonPath('data.customer_status.active', 2)
            ->assertJsonPath('data.customer_status.inactive', 0)
            ->assertJsonPath('data.customer_status.lead', 0)
            ->assertJsonPath('data.users_by_role.owner', 1)
            ->assertJsonPath('data.users_by_role.admin', 1)
            ->assertJsonPath('data.users_by_role.user', 0);
    }

    /**
     * Verify a regular user receives only the summary dashboard.
     */
    public function test_regular_user_receives_summary_only(): void
    {
        [$company] = $this->createDashboardTenant('free');

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::User,
            'status' => UserStatus::Active,
        ]);

        $this->runDashboardTenant(
            $company->id,
            function () use ($company): void {
                Customer::factory()->count(4)->create([
                    'company_id' => $company->id,
                    'status' => CustomerStatus::Active,
                ]);
            },
        );

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.users.used', 2)
            ->assertJsonPath('data.users.limit', 5)
            ->assertJsonPath('data.users.percent', 40)
            ->assertJsonPath('data.customers.used', 4)
            ->assertJsonPath('data.customers.limit', 100)
            ->assertJsonPath('data.customers.percent', 4)
            ->assertJsonPath('data.plan.slug', 'free')
            ->assertJsonPath('data.plan.name', 'Free')
            ->assertJsonMissingPath('data.customer_status')
            ->assertJsonMissingPath('data.users_by_role');
    }

    /**
     * Verify dashboard analytics are isolated between tenants.
     */
    public function test_dashboard_is_tenant_isolated(): void
    {
        [$companyA, $ownerA] = $this->createDashboardTenant('free');
        [$companyB] = $this->createDashboardTenant('free');

        $this->runDashboardTenant(
            $companyA->id,
            function () use ($companyA): void {
                Customer::factory()->count(3)->create([
                    'company_id' => $companyA->id,
                    'status' => CustomerStatus::Active,
                ]);
            },
        );

        $this->runDashboardTenant(
            $companyB->id,
            function () use ($companyB): void {
                Customer::factory()->count(7)->create([
                    'company_id' => $companyB->id,
                    'status' => CustomerStatus::Lead,
                ]);
            },
        );

        Sanctum::actingAs($ownerA);

        $response = $this->getJson('/api/v1/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.users.used', 1)
            ->assertJsonPath('data.customers.used', 3)
            ->assertJsonPath('data.customer_status.active', 3)
            ->assertJsonPath('data.customer_status.inactive', 0)
            ->assertJsonPath('data.customer_status.lead', 0);
    }

    /**
     * Verify dashboard calculates usage percentages correctly.
     */
    public function test_dashboard_calculates_usage_percentages(): void
    {
        [$company, $owner] = $this->createDashboardTenant('free');

        User::factory()->count(2)->create([
            'company_id' => $company->id,
            'role' => Role::User,
            'status' => UserStatus::Active,
        ]);

        $this->runDashboardTenant(
            $company->id,
            function () use ($company): void {
                Customer::factory()->count(25)->create([
                    'company_id' => $company->id,
                    'status' => CustomerStatus::Active,
                ]);
            },
        );

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.users.used', 3)
            ->assertJsonPath('data.users.limit', 5)
            ->assertJsonPath('data.users.percent', 60)
            ->assertJsonPath('data.customers.used', 25)
            ->assertJsonPath('data.customers.limit', 100)
            ->assertJsonPath('data.customers.percent', 25);
    }

    /**
     * Verify unlimited plans expose null limits and percentages.
     */
    public function test_dashboard_returns_null_percentage_for_unlimited_plan(): void
    {
        [$company, $owner] = $this->createDashboardTenant('enterprise');

        User::factory()->count(3)->create([
            'company_id' => $company->id,
            'role' => Role::User,
            'status' => UserStatus::Active,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.users.used', 4)
            ->assertJsonPath('data.users.limit', null)
            ->assertJsonPath('data.users.percent', null)
            ->assertJsonPath('data.customers.used', 0)
            ->assertJsonPath('data.customers.limit', null)
            ->assertJsonPath('data.customers.percent', null)
            ->assertJsonPath('data.plan.slug', 'enterprise')
            ->assertJsonPath('data.plan.name', 'Enterprise');
    }

    /**
     * Verify unauthenticated clients cannot access the dashboard.
     */
    public function test_unauthenticated_user_cannot_view_dashboard(): void
    {
        $response = $this->getJson('/api/v1/dashboard');

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    /**
     * Create a tenant with the requested plan.
     *
     * @return array{0: Company, 1: User}
     */
    private function createDashboardTenant(
        string $planSlug,
    ): array {
        /** @var Plan $plan */
        $plan = Plan::query()->firstOrCreate(
            ['slug' => $planSlug],
            [
                'name' => ucfirst($planSlug),
                'tier' => match ($planSlug) {
                    'free' => 1,
                    'pro' => 2,
                    'enterprise' => 3,
                    default => 1,
                },
                'price_cents' => 0,
                'max_users' => $planSlug === 'enterprise' ? null : 5,
                'max_customers' => $planSlug === 'enterprise' ? null : 100,
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
     * Execute a callback with the requested tenant context active.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function runDashboardTenant(
        int $companyId,
        Closure $callback,
    ): mixed {
        return app(TenantContext::class)->runAs(
            $companyId,
            $callback,
        );
    }
}
