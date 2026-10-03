<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Create a tenant with the existing plan.
 *
 * @return array{
 *     company: Company,
 *     owner: User,
 *     subscription: Subscription
 * }
 */
function createCustomerTestTenant(string $planSlug = 'free'): array
{
    /** @var Plan $plan */
    $plan = Plan::query()->firstOrCreate(
        ['slug' => $planSlug],
        [
            'name' => ucfirst($planSlug),
            'tier' => $planSlug === 'free' ? 1 : 2,
            'price_cents' => 0,
            'max_users' => $planSlug === 'free' ? 5 : 50,
            'max_customers' => $planSlug === 'free' ? 100 : 5000,
            'features' => [],
            'is_active' => true,
        ],
    );

    $company = Company::factory()->create();

    $owner = User::factory()->create([
        'company_id' => $company->id,
        'role' => Role::Owner,
    ]);

    $subscription = Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => Carbon::now(),
    ]);

    return [
        'company' => $company,
        'owner' => $owner,
        'subscription' => $subscription,
    ];
}

/**
 * Execute a callback with the requested tenant context active.
 *
 * @template T
 *
 * @param  Closure(): T  $callback
 * @return T
 */
function runCustomerTenant(int $companyId, Closure $callback): mixed
{
    return app(TenantContext::class)->runAs($companyId, $callback);
}

/**
 * Create an authenticated user for a company.
 */
function createCustomerTestUser(
    Company $company,
    Role $role = Role::User,
): User {
    return User::factory()->create([
        'company_id' => $company->id,
        'role' => $role,
    ]);
}

it('allows an authenticated user to create a customer', function (): void {
    $tenant = createCustomerTestTenant();

    $response = $this
        ->actingAs($tenant['owner'])
        ->postJson('/api/v1/customers', [
            'name' => 'John Doe',
            'email' => 'John@Example.com',
            'phone' => '+8801712345678',
            'status' => CustomerStatus::Active->value,
            'notes' => 'Important customer',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'John Doe')
        ->assertJsonPath('data.email', 'john@example.com');

    $this->assertDatabaseHas('customers', [
        'company_id' => $tenant['company']->id,
        'email' => 'john@example.com',
    ]);
});

it('allows customers to be listed with filtering and pagination', function (): void {
    $tenant = createCustomerTestTenant();

    runCustomerTenant(
        $tenant['company']->id,
        function (): void {
            Customer::factory()->create([
                'name' => 'Alice Smith',
                'email' => 'alice@example.com',
                'status' => CustomerStatus::Active,
            ]);

            Customer::factory()->create([
                'name' => 'Bob Smith',
                'email' => 'bob@example.com',
                'status' => CustomerStatus::Lead,
            ]);

            Customer::factory()->create([
                'name' => 'Charlie Brown',
                'email' => 'charlie@example.com',
                'status' => CustomerStatus::Inactive,
            ]);
        },
    );

    $response = $this
        ->actingAs($tenant['owner'])
        ->getJson('/api/v1/customers?status=active&search=Alice&per_page=1&page=1');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.email', 'alice@example.com');
});

it('allows a user to view a customer', function (): void {
    $tenant = createCustomerTestTenant();

    $customer = runCustomerTenant(
        $tenant['company']->id,
        fn (): Customer => Customer::factory()->create(),
    );

    $user = createCustomerTestUser($tenant['company']);

    $this
        ->actingAs($user)
        ->getJson("/api/v1/customers/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $customer->id);
});

it('allows a user to update a customer', function (): void {
    $tenant = createCustomerTestTenant();

    $customer = runCustomerTenant(
        $tenant['company']->id,
        fn (): Customer => Customer::factory()->create(),
    );

    $user = createCustomerTestUser($tenant['company']);

    $response = $this
        ->actingAs($user)
        ->putJson("/api/v1/customers/{$customer->id}", [
            'name' => 'Updated Customer',
            'email' => 'updated@example.com',
            'phone' => '+8801800000000',
            'status' => CustomerStatus::Inactive->value,
            'notes' => 'Updated notes',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Customer')
        ->assertJsonPath('data.email', 'updated@example.com')
        ->assertJsonPath('data.status', 'inactive');
});

it('allows the owner to delete a customer', function (): void {
    $tenant = createCustomerTestTenant();

    $customer = runCustomerTenant(
        $tenant['company']->id,
        fn (): Customer => Customer::factory()->create(),
    );

    $this
        ->actingAs($tenant['owner'])
        ->deleteJson("/api/v1/customers/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertSoftDeleted('customers', [
        'id' => $customer->id,
    ]);
});

it('allows an admin to delete a customer', function (): void {
    $tenant = createCustomerTestTenant();

    $customer = runCustomerTenant(
        $tenant['company']->id,
        fn (): Customer => Customer::factory()->create(),
    );

    $admin = createCustomerTestUser(
        $tenant['company'],
        Role::Admin,
    );

    $this
        ->actingAs($admin)
        ->deleteJson("/api/v1/customers/{$customer->id}")
        ->assertOk();

    $this->assertSoftDeleted('customers', [
        'id' => $customer->id,
    ]);
});

it('does not allow a normal user to delete a customer', function (): void {
    $tenant = createCustomerTestTenant();

    $customer = runCustomerTenant(
        $tenant['company']->id,
        fn (): Customer => Customer::factory()->create(),
    );

    $user = createCustomerTestUser($tenant['company']);

    $response = $this
        ->actingAs($user)
        ->deleteJson("/api/v1/customers/{$customer->id}");

    $response
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'FORBIDDEN');

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'deleted_at' => null,
    ]);
});

it('prevents duplicate customer emails within the same company', function (): void {
    $tenant = createCustomerTestTenant();

    runCustomerTenant(
        $tenant['company']->id,
        function (): void {
            Customer::factory()->create([
                'email' => 'existing@example.com',
            ]);
        },
    );

    $response = $this
        ->actingAs($tenant['owner'])
        ->postJson('/api/v1/customers', [
            'name' => 'Another Customer',
            'email' => 'EXISTING@example.com',
            'status' => CustomerStatus::Active->value,
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

it('allows the same customer email in different companies', function (): void {
    $firstTenant = createCustomerTestTenant();
    $secondTenant = createCustomerTestTenant();

    runCustomerTenant(
        $firstTenant['company']->id,
        function (): void {
            Customer::factory()->create([
                'email' => 'same@example.com',
            ]);
        },
    );

    $response = $this
        ->actingAs($secondTenant['owner'])
        ->postJson('/api/v1/customers', [
            'name' => 'Second Tenant Customer',
            'email' => 'same@example.com',
            'status' => CustomerStatus::Active->value,
        ]);

    $response->assertCreated();
});

it('allows reuse of an email after soft deleting the previous customer', function (): void {
    $tenant = createCustomerTestTenant();

    $customer = runCustomerTenant(
        $tenant['company']->id,
        fn (): Customer => Customer::factory()->create([
            'email' => 'reusable@example.com',
        ]),
    );

    runCustomerTenant(
        $tenant['company']->id,
        function () use ($customer): void {
            $customer->delete();
        },
    );

    $response = $this
        ->actingAs($tenant['owner'])
        ->postJson('/api/v1/customers', [
            'name' => 'Replacement Customer',
            'email' => 'REUSABLE@example.com',
            'status' => CustomerStatus::Active->value,
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.email', 'reusable@example.com');
});

it('does not expose another company customer', function (): void {
    $firstTenant = createCustomerTestTenant();
    $secondTenant = createCustomerTestTenant();

    $customer = runCustomerTenant(
        $firstTenant['company']->id,
        fn (): Customer => Customer::factory()->create(),
    );

    $this
        ->actingAs($secondTenant['owner'])
        ->getJson("/api/v1/customers/{$customer->id}")
        ->assertNotFound();

    $this
        ->actingAs($secondTenant['owner'])
        ->putJson("/api/v1/customers/{$customer->id}", [
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'status' => CustomerStatus::Active->value,
        ])
        ->assertNotFound();

    $this
        ->actingAs($secondTenant['owner'])
        ->deleteJson("/api/v1/customers/{$customer->id}")
        ->assertNotFound();
});

it('does not count soft deleted customers against the plan limit', function (): void {
    $tenant = createCustomerTestTenant();

    $customer = runCustomerTenant(
        $tenant['company']->id,
        function (): Customer {
            Customer::factory()->count(100)->create();

            /** @var Customer $customer */
            $customer = Customer::query()->firstOrFail();

            return $customer;
        },
    );

    runCustomerTenant(
        $tenant['company']->id,
        function () use ($customer): void {
            $customer->delete();
        },
    );

    $response = $this
        ->actingAs($tenant['owner'])
        ->postJson('/api/v1/customers', [
            'name' => 'New Customer',
            'email' => 'new@example.com',
            'status' => CustomerStatus::Active->value,
        ]);

    $response->assertCreated();
});

it('rejects customer creation when the plan limit is reached', function (): void {
    $tenant = createCustomerTestTenant();

    runCustomerTenant(
        $tenant['company']->id,
        function (): void {
            Customer::factory()->count(100)->create();
        },
    );

    $response = $this
        ->actingAs($tenant['owner'])
        ->postJson('/api/v1/customers', [
            'name' => 'One Too Many',
            'email' => 'limit@example.com',
            'status' => CustomerStatus::Active->value,
        ]);

    $response
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'PLAN_LIMIT_REACHED')
        ->assertJsonPath('error.details.resource', 'customers')
        ->assertJsonPath('error.details.used', 100)
        ->assertJsonPath('error.details.limit', 100);
});

it('rejects invalid customer status', function (): void {
    $tenant = createCustomerTestTenant();

    $response = $this
        ->actingAs($tenant['owner'])
        ->postJson('/api/v1/customers', [
            'name' => 'Invalid Status',
            'email' => 'invalid@example.com',
            'status' => 'unknown',
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

it('rejects per page values above the API maximum', function (): void {
    $tenant = createCustomerTestTenant();

    $response = $this
        ->actingAs($tenant['owner'])
        ->getJson('/api/v1/customers?per_page=101');

    $response
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

it('sorts customers by the requested field', function (): void {
    $tenant = createCustomerTestTenant();

    runCustomerTenant(
        $tenant['company']->id,
        function (): void {
            Customer::factory()->create([
                'name' => 'Zebra',
            ]);

            Customer::factory()->create([
                'name' => 'Alpha',
            ]);
        },
    );

    $response = $this
        ->actingAs($tenant['owner'])
        ->getJson('/api/v1/customers?sort=name');

    $response
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Alpha')
        ->assertJsonPath('data.1.name', 'Zebra');
});
