<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

function createFreePlan(): Plan
{
    return Plan::query()->create([
        'slug' => 'free',
        'name' => 'Free',
        'tier' => 1,
        'price_cents' => 0,
        'max_users' => 5,
        'max_customers' => 100,
        'features' => [],
        'is_active' => true,
    ]);
}

function createCompanyWithOwner(
    string $email = 'owner@example.com',
    UserStatus $status = UserStatus::Active,
): array {
    $company = Company::query()->create([
        'name' => 'Acme Ltd',
        'slug' => 'acme-ltd',
    ]);

    $user = User::query()->create([
        'company_id' => $company->id,
        'name' => 'Owner',
        'email' => $email,
        'password' => 'password123',
        'role' => Role::Owner,
        'status' => $status,
    ]);

    return [$company, $user];
}

/*
|--------------------------------------------------------------------------
| Register company
|--------------------------------------------------------------------------
*/

it('registers a company with an owner and free subscription', function (): void {
    createFreePlan();

    $response = $this->postJson('/api/v1/auth/register-company', [
        'company_name' => 'Acme Ltd',
        'company_slug' => 'acme-ltd',
        'name' => 'John Owner',
        'email' => 'John@Example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token', fn ($token) => is_string($token) && $token !== '');

    $this->assertDatabaseHas('companies', [
        'name' => 'Acme Ltd',
        'slug' => 'acme-ltd',
    ]);

    $company = Company::query()
        ->where('slug', 'acme-ltd')
        ->firstOrFail();

    $this->assertDatabaseHas('users', [
        'company_id' => $company->id,
        'name' => 'John Owner',
        'email' => 'john@example.com',
        'role' => Role::Owner->value,
        'status' => UserStatus::Active->value,
    ]);

    $this->assertDatabaseHas('subscriptions', [
        'company_id' => $company->id,
        'plan_id' => Plan::query()->where('slug', 'free')->value('id'),
        'status' => SubscriptionStatus::Active->value,
    ]);

    expect(PersonalAccessToken::query()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Register transaction rollback
|--------------------------------------------------------------------------
*/

it('rolls back company and owner creation when registration fails', function (): void {
    // Deliberately do not create the Free plan.
    // Company creation succeeds inside the transaction, then the service
    // must fail when it cannot find the required Free plan.

    $response = $this->postJson('/api/v1/auth/register-company', [
        'company_name' => 'Rollback Ltd',
        'company_slug' => 'rollback-ltd',
        'name' => 'Rollback Owner',
        'email' => 'rollback@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertServerError();

    $this->assertDatabaseMissing('companies', [
        'slug' => 'rollback-ltd',
    ]);

    $this->assertDatabaseMissing('users', [
        'email' => 'rollback@example.com',
    ]);

    $this->assertDatabaseMissing('subscriptions', [
        'company_id' => Company::query()
            ->where('slug', 'rollback-ltd')
            ->value('id'),
    ]);
});

/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

it('logs in with valid credentials', function (): void {
    [$company, $user] = createCompanyWithOwner();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'OWNER@EXAMPLE.COM',
        'password' => 'password123',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token', fn ($token) => is_string($token) && $token !== '');

    expect($user->fresh()->email)->toBe('owner@example.com');
});

it('returns the same generic 401 response for a wrong password', function (): void {
    createCompanyWithOwner();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'owner@example.com',
        'password' => 'wrong-password',
    ]);

    $response
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
});

it('returns the same generic 401 response for an unknown email', function (): void {
    createCompanyWithOwner();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'does-not-exist@example.com',
        'password' => 'password123',
    ]);

    $response
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
});

it('returns the same generic 401 response for an inactive user', function (): void {
    createCompanyWithOwner(
        email: 'inactive@example.com',
        status: UserStatus::Inactive,
    );

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'inactive@example.com',
        'password' => 'password123',
    ]);

    $response
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
});

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

it('revokes only the current token on logout', function (): void {
    [$company, $user] = createCompanyWithOwner();

    $tokenA = $user->createToken('device-a')->plainTextToken;
    $tokenB = $user->createToken('device-b')->plainTextToken;

    expect(PersonalAccessToken::query()->count())->toBe(2);

    $response = $this
        ->withToken($tokenA)
        ->postJson('/api/v1/auth/logout');

    $response
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(PersonalAccessToken::query()->count())->toBe(1);

    $this->assertDatabaseHas('personal_access_tokens', [
        'name' => 'device-b',
    ]);

    $this->assertDatabaseMissing('personal_access_tokens', [
        'name' => 'device-a',
    ]);
});

/*
|--------------------------------------------------------------------------
| Me
|--------------------------------------------------------------------------
*/

it('returns the authenticated user from the current token', function (): void {
    [$company, $user] = createCompanyWithOwner();

    $token = $user->createToken('api')->plainTextToken;

    $response = $this
        ->withToken($token)
        ->getJson('/api/v1/auth/me');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'Owner')
        ->assertJsonPath('data.email', 'owner@example.com')
        ->assertJsonMissingPath('data.password');
});

/*
|--------------------------------------------------------------------------
| Security
|--------------------------------------------------------------------------
*/

it('stores email in lowercase and treats email as case insensitive', function (): void {
    createFreePlan();

    $this->postJson('/api/v1/auth/register-company', [
        'company_name' => 'Acme Ltd',
        'company_slug' => 'acme-ltd',
        'name' => 'Owner',
        'email' => 'Owner@Example.COM',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $this->assertDatabaseHas('users', [
        'email' => 'owner@example.com',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'OWNER@EXAMPLE.COM',
        'password' => 'password123',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('allows only one owner per company', function (): void {
    [$company, $owner] = createCompanyWithOwner();

    expect(fn () => User::query()->create([
        'company_id' => $company->id,
        'name' => 'Second Owner',
        'email' => 'second-owner@example.com',
        'password' => 'password123',
        'role' => Role::Owner,
        'status' => UserStatus::Active,
    ]))->toThrow(QueryException::class);
});

it('does not allow company_id to be supplied through registration input', function (): void {
    createFreePlan();

    $attackerCompany = Company::query()->create([
        'name' => 'Attacker Company',
        'slug' => 'attacker-company',
    ]);

    $response = $this->postJson('/api/v1/auth/register-company', [
        'company_name' => 'Victim Company',
        'company_slug' => 'victim-company',
        'name' => 'Owner',
        'email' => 'owner@victim.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'company_id' => $attackerCompany->id,
    ]);

    $response->assertCreated();

    $victimCompany = Company::query()
        ->where('slug', 'victim-company')
        ->firstOrFail();

    $user = User::query()
        ->where('email', 'owner@victim.com')
        ->firstOrFail();

    expect($user->company_id)
        ->toBe($victimCompany->id)
        ->not->toBe($attackerCompany->id);
});

it('returns 401 for an unauthenticated request to me', function (): void {
    $this
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

it('returns 401 for an unauthenticated request to logout', function (): void {
    $this
        ->postJson('/api/v1/auth/logout')
        ->assertUnauthorized();
});
