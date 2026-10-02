<?php

declare(strict_types=1);

namespace Tests\Unit\Limits;

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Limits\LimitEnforcer;
use App\Limits\UserLimitCheck;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers subscription resource-limit enforcement independently
 * from HTTP controllers and API responses.
 */
final class LimitEnforcerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that a user creation is rejected at the plan limit.
     */
    public function test_user_limit_is_enforced(): void
    {
        $company = Company::factory()->create();

        $plan = Plan::factory()->create([
            'slug' => 'free',
            'name' => 'Free',
            'tier' => 1,
            'max_users' => 5,
            'max_customers' => 100,
            'is_active' => true,
        ]);

        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now(),
        ]);

        User::factory()->count(5)->create([
            'company_id' => $company->id,
            'role' => Role::User,
            'status' => UserStatus::Active,
        ]);

        $enforcer = new LimitEnforcer([
            new UserLimitCheck,
        ]);

        $this->expectException(BusinessRuleException::class);

        try {
            $enforcer->ensureCanCreate($company, 'users');
        } catch (BusinessRuleException $exception) {
            $this->assertSame(
                'PLAN_LIMIT_REACHED',
                $exception->errorCode(),
            );

            $this->assertSame(403, $exception->status());

            $this->assertSame(
                [
                    'resource' => 'users',
                    'used' => 5,
                    'limit' => 5,
                    'plan' => 'free',
                ],
                $exception->details(),
            );

            throw $exception;
        }
    }

    /**
     * Verify that unlimited resources are never rejected.
     */
    public function test_unlimited_plan_allows_creation(): void
    {
        $company = Company::factory()->create();

        $plan = Plan::factory()->create([
            'slug' => 'enterprise',
            'name' => 'Enterprise',
            'tier' => 3,
            'max_users' => null,
            'max_customers' => null,
            'is_active' => true,
        ]);

        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now(),
        ]);

        User::factory()->count(20)->create([
            'company_id' => $company->id,
            'role' => Role::User,
            'status' => UserStatus::Active,
        ]);

        $enforcer = new LimitEnforcer([
            new UserLimitCheck,
        ]);

        $enforcer->ensureCanCreate($company, 'users');

        $this->assertTrue(true);
    }

    /**
     * Verify that an unknown resource cannot silently bypass enforcement.
     */
    public function test_unknown_resource_is_rejected(): void
    {
        $company = Company::factory()->create();

        $plan = Plan::factory()->create([
            'slug' => 'free',
            'name' => 'Free',
            'tier' => 1,
            'max_users' => 5,
            'max_customers' => 100,
            'is_active' => true,
        ]);

        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now(),
        ]);

        $enforcer = new LimitEnforcer([
            new UserLimitCheck,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $enforcer->ensureCanCreate($company, 'unknown-resource');
    }
}
