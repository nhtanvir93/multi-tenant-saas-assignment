<?php

declare(strict_types=1);

namespace Tests\Feature;

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
 * Covers tenant user CRUD, RBAC, tenant isolation and plan limits.
 */
final class UsersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that all tenant roles can list users.
     */
    public function test_all_roles_can_list_users(): void
    {
        [$company, $owner] = $this->createTenant();

        $admin = $this->createUser(
            $company,
            Role::Admin,
        );

        $user = $this->createUser(
            $company,
            Role::User,
        );

        $users = [$owner, $admin, $user];

        foreach ($users as $actor) {
            Sanctum::actingAs($actor);

            $this->getJson('/api/v1/users')
                ->assertOk()
                ->assertJsonPath('success', true);
        }
    }

    /**
     * Owner can create an admin.
     */
    public function test_owner_can_create_admin(): void
    {
        [$company, $owner] = $this->createTenant();

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'New Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'role' => Role::Admin->value,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.role', Role::Admin->value);

        $this->assertDatabaseHas('users', [
            'company_id' => $company->id,
            'email' => 'admin@example.com',
            'role' => Role::Admin->value,
        ]);
    }

    /**
     * Admin can create a regular user.
     */
    public function test_admin_can_create_user(): void
    {
        [$company] = $this->createTenant();

        $admin = $this->createUser(
            $company,
            Role::Admin,
        );

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => 'password123',
            'role' => Role::User->value,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.role', Role::User->value);
    }

    /**
     * Admin cannot create another admin.
     */
    public function test_admin_cannot_create_admin(): void
    {
        [$company] = $this->createTenant();

        $admin = $this->createUser(
            $company,
            Role::Admin,
        );

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/users', [
            'name' => 'Second Admin',
            'email' => 'second-admin@example.com',
            'password' => 'password123',
            'role' => Role::Admin->value,
        ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * Regular users cannot create users.
     */
    public function test_regular_user_cannot_create_user(): void
    {
        [$company] = $this->createTenant();

        $user = $this->createUser(
            $company,
            Role::User,
        );

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/users', [
            'name' => 'Another User',
            'email' => 'another@example.com',
            'password' => 'password123',
            'role' => Role::User->value,
        ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * Owner can update an admin.
     */
    public function test_owner_can_update_admin(): void
    {
        [$company, $owner] = $this->createTenant();

        $admin = $this->createUser(
            $company,
            Role::Admin,
        );

        Sanctum::actingAs($owner);

        $this->putJson(
            "/api/v1/users/{$admin->id}",
            [
                'name' => 'Updated Admin',
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Admin');
    }

    /**
     * Admin cannot update another admin.
     */
    public function test_admin_cannot_update_another_admin(): void
    {
        [$company] = $this->createTenant();

        $admin = $this->createUser(
            $company,
            Role::Admin,
        );

        $secondAdmin = $this->createUser(
            $company,
            Role::Admin,
        );

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/v1/users/{$secondAdmin->id}",
            [
                'name' => 'Changed',
            ],
        )
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * A user cannot change their own role.
     */
    public function test_user_cannot_change_own_role(): void
    {
        [$company, $owner] = $this->createTenant();

        Sanctum::actingAs($owner);

        $this->putJson(
            "/api/v1/users/{$owner->id}",
            [
                'role' => Role::Admin->value,
            ],
        )
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * Owner cannot be deleted.
     */
    public function test_owner_cannot_be_deleted(): void
    {
        [$company, $owner] = $this->createTenant();

        $admin = $this->createUser(
            $company,
            Role::Admin,
        );

        Sanctum::actingAs($admin);

        $this->deleteJson(
            "/api/v1/users/{$owner->id}",
        )
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * A user cannot delete themselves.
     */
    public function test_user_cannot_delete_themselves(): void
    {
        [$company, $owner] = $this->createTenant();

        $admin = $this->createUser(
            $company,
            Role::Admin,
        );

        Sanctum::actingAs($admin);

        $this->deleteJson(
            "/api/v1/users/{$admin->id}",
        )
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * Users belonging to another company cannot be viewed.
     */
    public function test_cross_tenant_user_returns_not_found(): void
    {
        [, $ownerA] = $this->createTenant();

        [$companyB] = $this->createTenant();

        $userB = $this->createUser(
            $companyB,
            Role::User,
        );

        Sanctum::actingAs($ownerA);

        $this->getJson(
            "/api/v1/users/{$userB->id}",
        )
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    /**
     * Free-plan user limit rejects the sixth user.
     */
    public function test_free_plan_rejects_user_at_limit(): void
    {
        [$company, $owner] = $this->createTenant();

        for ($i = 0; $i < 4; $i++) {
            $this->createUser(
                $company,
                Role::User,
            );
        }

        /** @var Subscription $subscription */
        $subscription = $company->subscriptions()
            ->where('status', 'active')
            ->with('plan')
            ->firstOrFail();

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/users', [
            'name' => 'Sixth User',
            'email' => 'sixth@example.com',
            'password' => 'password123',
            'role' => Role::User->value,
        ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PLAN_LIMIT_REACHED')
            ->assertJsonPath('error.details.resource', 'users')
            ->assertJsonPath('error.details.used', 5)
            ->assertJsonPath('error.details.limit', 5)
            ->assertJsonPath(
                'error.details.plan',
                $subscription->plan->slug,
            );
    }

    /**
     * Inactive users still consume seats.
     */
    public function test_inactive_user_still_consumes_plan_seat(): void
    {
        [$company, $owner] = $this->createTenant();

        for ($i = 0; $i < 4; $i++) {
            $this->createUser(
                $company,
                Role::User,
                $i === 0 ? UserStatus::Inactive : UserStatus::Active,
            );
        }

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/users', [
            'name' => 'Sixth User',
            'email' => 'sixth@example.com',
            'password' => 'password123',
            'role' => Role::User->value,
        ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'PLAN_LIMIT_REACHED');
    }

    /**
     * Deleting a user frees a plan seat.
     */
    public function test_deleting_user_frees_plan_seat(): void
    {
        [$company, $owner] = $this->createTenant();

        $users = [];

        for ($i = 0; $i < 4; $i++) {
            $users[] = $this->createUser(
                $company,
                Role::User,
            );
        }

        Sanctum::actingAs($owner);

        $this->deleteJson(
            "/api/v1/users/{$users[0]->id}",
        )->assertOk();

        $this->postJson('/api/v1/users', [
            'name' => 'Replacement User',
            'email' => 'replacement@example.com',
            'password' => 'password123',
            'role' => Role::User->value,
        ])
            ->assertCreated();
    }

    /**
     * Inactive users cannot log in.
     */
    public function test_inactive_user_cannot_login(): void
    {
        [$company] = $this->createTenant();

        $user = $this->createUser(
            $company,
            Role::User,
            UserStatus::Inactive,
        );

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    /**
     * Create a company with a Free subscription.
     *
     * @return array{0: Company, 1: User}
     */
    private function createTenant(): array
    {
        $company = Company::factory()->create();

        $plan = Plan::factory()->create([
            'slug' => 'free-'.$company->id,
            'name' => 'Free',
            'tier' => $company->id + 10,
            'max_users' => 5,
            'max_customers' => 100,
            'is_active' => true,
        ]);

        $owner = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Owner',
            'email' => 'owner-'.$company->id.'@example.com',
            'password' => bcrypt('password123'),
            'role' => Role::Owner,
            'status' => UserStatus::Active,
        ]);

        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now(),
        ]);

        return [$company, $owner];
    }

    /**
     * Create a user belonging to the supplied company.
     */
    private function createUser(
        Company $company,
        Role $role,
        UserStatus $status = UserStatus::Active,
    ): User {
        return User::factory()->create([
            'company_id' => $company->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password123'),
            'role' => $role,
            'status' => $status,
        ]);
    }
}
