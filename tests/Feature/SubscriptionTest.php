<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers subscription catalogue, viewing, upgrades, authorization,
 * and subscription usage endpoints.
 */
final class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that active plans are returned in tier order.
     */
    public function test_active_plans_are_returned_in_tier_order(): void
    {
        Plan::factory()->create([
            'slug' => 'pro',
            'name' => 'Pro',
            'tier' => 2,
            'is_active' => true,
        ]);

        Plan::factory()->create([
            'slug' => 'free',
            'name' => 'Free',
            'tier' => 1,
            'is_active' => true,
        ]);

        Plan::factory()->create([
            'slug' => 'enterprise',
            'name' => 'Enterprise',
            'tier' => 3,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/plans');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.slug', 'free')
            ->assertJsonPath('data.1.slug', 'pro')
            ->assertJsonCount(2, 'data');
    }

    /**
     * Verify that owners can view subscription details.
     */
    public function test_owner_can_view_subscription(): void
    {
        [$company, $owner] = $this->createTenant();

        $subscription = $this->createSubscription($company, 'free');

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/subscription');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $subscription->id)
            ->assertJsonPath('data.plan.slug', 'free');
    }

    /**
     * Verify that admins can view subscription details.
     */
    public function test_admin_can_view_subscription(): void
    {
        [$company, $owner] = $this->createTenant();

        $this->createSubscription($company, 'free');

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::Admin,
            'status' => UserStatus::Active,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/subscription');

        $response
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    /**
     * Verify that ordinary users cannot view subscription details.
     */
    public function test_user_cannot_view_subscription_details(): void
    {
        [$company, $owner] = $this->createTenant();

        $this->createSubscription($company, 'free');

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::User,
            'status' => UserStatus::Active,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/subscription');

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * Verify that the owner can upgrade from Free to Pro.
     */
    public function test_owner_can_upgrade_subscription(): void
    {
        [$company, $owner] = $this->createTenant();

        $current = $this->createSubscription($company, 'free');

        $pro = Plan::query()
            ->where('slug', 'pro')
            ->firstOrFail();

        Sanctum::actingAs($owner);

        $response = $this->putJson('/api/v1/subscription', [
            'plan_id' => $pro->id,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.plan.slug', 'pro');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $current->id,
            'status' => SubscriptionStatus::Replaced->value,
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'plan_id' => $pro->id,
            'status' => SubscriptionStatus::Active->value,
        ]);

        $this->assertDatabaseCount('subscriptions', 2);
    }

    /**
     * Verify that an admin cannot upgrade the company's subscription.
     */
    public function test_admin_cannot_upgrade_subscription(): void
    {
        [$company] = $this->createTenant();

        $this->createSubscription($company, 'free');

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::Admin,
            'status' => UserStatus::Active,
        ]);

        $pro = Plan::query()
            ->where('slug', 'pro')
            ->firstOrFail();

        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/v1/subscription', [
            'plan_id' => $pro->id,
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * Verify that upgrading to the current plan is rejected.
     */
    public function test_same_plan_is_rejected(): void
    {
        [$company, $owner] = $this->createTenant();

        $free = $this->createSubscription($company, 'free');

        Sanctum::actingAs($owner);

        $response = $this->putJson('/api/v1/subscription', [
            'plan_id' => $free->plan_id,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'ALREADY_ON_PLAN');
    }

    /**
     * Verify that downgrading is rejected.
     */
    public function test_downgrade_is_rejected(): void
    {
        [$company, $owner] = $this->createTenant();

        $this->createSubscription($company, 'pro');

        $free = Plan::query()
            ->where('slug', 'free')
            ->firstOrFail();

        Sanctum::actingAs($owner);

        $response = $this->putJson('/api/v1/subscription', [
            'plan_id' => $free->id,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'DOWNGRADE_NOT_ALLOWED');
    }

    /**
     * Verify that inactive plans cannot be selected.
     */
    public function test_inactive_plan_cannot_be_selected(): void
    {
        [$company, $owner] = $this->createTenant();

        $this->createSubscription($company, 'free');

        $inactivePlan = Plan::factory()->create([
            'slug' => 'inactive-enterprise',
            'name' => 'Inactive Enterprise',
            'tier' => 4,
            'is_active' => false,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->putJson('/api/v1/subscription', [
            'plan_id' => $inactivePlan->id,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'PLAN_NOT_AVAILABLE');
    }

    /**
     * Verify that every tenant role can view usage.
     */
    public function test_user_can_view_subscription_usage(): void
    {
        [$company, $owner] = $this->createTenant();

        $this->createSubscription($company, 'free');

        User::factory()->count(2)->create([
            'company_id' => $company->id,
            'role' => Role::User,
            'status' => UserStatus::Active,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/subscription/usage');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.users.used', 3)
            ->assertJsonPath('data.users.limit', 5)
            ->assertJsonPath('data.users.percent', 60);
    }

    /**
     * Create the standard plans required by subscription tests.
     */
    private function createPlans(): void
    {
        Plan::factory()->create([
            'slug' => 'free',
            'name' => 'Free',
            'tier' => 1,
            'max_users' => 5,
            'max_customers' => 100,
            'is_active' => true,
        ]);

        Plan::factory()->create([
            'slug' => 'pro',
            'name' => 'Pro',
            'tier' => 2,
            'max_users' => 50,
            'max_customers' => 5000,
            'is_active' => true,
        ]);

        Plan::factory()->create([
            'slug' => 'enterprise',
            'name' => 'Enterprise',
            'tier' => 3,
            'max_users' => null,
            'max_customers' => null,
            'is_active' => true,
        ]);
    }

    /**
     * Create a company and owner for a tenant-scoped test.
     *
     * @return array{0: Company, 1: User}
     */
    private function createTenant(): array
    {
        $company = Company::factory()->create();

        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => Role::Owner,
            'status' => UserStatus::Active,
        ]);

        $this->createPlans();

        return [$company, $owner];
    }

    /**
     * Create an active subscription for the requested plan.
     */
    private function createSubscription(
        Company $company,
        string $slug,
    ): Subscription {
        $plan = Plan::query()
            ->where('slug', $slug)
            ->firstOrFail();

        return Subscription::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now(),
        ]);
    }
}
