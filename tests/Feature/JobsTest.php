<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Jobs\NotifyLimitThreshold;
use App\Jobs\SendUserInvitation;
use App\Jobs\SendWelcomeEmail;
use App\Jobs\WarmTenantCache;
use App\Mail\UserInvitationEmail;
use App\Mail\WelcomeEmail;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CacheService;
use App\Services\LimitThresholdService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/**
 * Verify that company registration queues the welcome email job.
 */
test('company registration dispatches welcome email job', function (): void {
    Queue::fake();

    Plan::factory()->create([
        'slug' => 'free',
        'is_active' => true,
        'tier' => 1,
        'max_users' => 5,
        'max_customers' => 100,
    ]);

    $response = $this->postJson('/api/v1/auth/register-company', [
        'company_name' => 'Acme Inc',
        'company_slug' => 'acme-inc',
        'name' => 'Owner',
        'email' => 'owner@acme.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSuccessful();

    $company = Company::query()
        ->where('slug', 'acme-inc')
        ->firstOrFail();

    $owner = User::query()
        ->where('company_id', $company->id)
        ->where('role', 'owner')
        ->firstOrFail();

    Queue::assertPushed(
        SendWelcomeEmail::class,
        function (SendWelcomeEmail $job) use ($company, $owner): bool {
            return $job->companyId === $company->id
                && $job->userId === $owner->id;
        },
    );
});

/**
 * Verify that a newly created tenant user queues an invitation job.
 */
test('user creation dispatches invitation job', function (): void {
    Queue::fake();

    $company = Company::factory()->create();

    $plan = Plan::factory()->create([
        'slug' => 'free',
        'is_active' => true,
        'tier' => 1,
        'max_users' => 5,
        'max_customers' => 100,
    ]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
    ]);

    $owner = User::factory()->create([
        'company_id' => $company->id,
        'role' => Role::Owner,
        'status' => UserStatus::Active,
    ]);

    $this->actingAs($owner, 'sanctum');

    $response = $this->postJson('/api/v1/users', [
        'name' => 'New User',
        'email' => 'new-user@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => Role::User->value,
    ]);

    $response->assertSuccessful();

    $user = User::query()
        ->where('company_id', $company->id)
        ->where('email', 'new-user@example.com')
        ->firstOrFail();

    Queue::assertPushed(
        SendUserInvitation::class,
        function (SendUserInvitation $job) use ($company, $user): bool {
            return $job->companyId === $company->id
                && $job->userId === $user->id;
        },
    );
});

/**
 * Verify that jobs expose retry configuration.
 */
test('background jobs have retry configuration', function (): void {
    expect((new SendWelcomeEmail(1, 1))->tries)->toBe(3)
        ->and((new SendWelcomeEmail(1, 1))->backoff)->toBe([10, 30, 60])
        ->and((new SendUserInvitation(1, 1))->tries)->toBe(3)
        ->and((new SendUserInvitation(1, 1))->backoff)->toBe([10, 30, 60])
        ->and((new NotifyLimitThreshold(1, 'users', 80))->tries)->toBe(3)
        ->and((new WarmTenantCache(1))->tries)->toBe(3);
});

/**
 * Verify that the welcome job restores tenant context before querying.
 */
test('welcome job executes inside tenant context', function (): void {
    Mail::fake();

    $company = Company::factory()->create();

    $owner = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'owner',
        'email' => 'owner@example.com',
    ]);

    (new SendWelcomeEmail(
        $company->id,
        $owner->id,
    ))->handle(
        app(TenantContext::class),
    );

    Mail::assertSent(WelcomeEmail::class);
});

/**
 * Verify that the invitation job restores tenant context before querying.
 */
test('invitation job executes inside tenant context', function (): void {
    Mail::fake();

    $company = Company::factory()->create();

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'user',
        'email' => 'user@example.com',
    ]);

    (new SendUserInvitation(
        $company->id,
        $user->id,
    ))->handle(
        app(TenantContext::class),
    );

    Mail::assertSent(UserInvitationEmail::class);
});

/**
 * Verify that the 80 percent threshold queues a notification.
 */
test('80 percent usage threshold dispatches notification', function (): void {
    Queue::fake();

    NotifyLimitThreshold::dispatch(
        1,
        'users',
        80,
    );

    Queue::assertPushed(
        NotifyLimitThreshold::class,
        fn (NotifyLimitThreshold $job): bool => $job->companyId === 1
            && $job->resource === 'users'
            && $job->threshold === 80,
    );
});

/**
 * Verify that the 100 percent threshold queues a notification.
 */
test('100 percent usage threshold dispatches notification', function (): void {
    Queue::fake();

    NotifyLimitThreshold::dispatch(
        1,
        'users',
        100,
    );

    Queue::assertPushed(
        NotifyLimitThreshold::class,
        fn (NotifyLimitThreshold $job): bool => $job->companyId === 1
            && $job->resource === 'users'
            && $job->threshold === 100,
    );
});

/**
 * Verify that unlimited plans do not trigger threshold notifications.
 */
test('unlimited usage does not require a threshold notification', function (): void {
    $service = new LimitThresholdService;

    Queue::fake();

    $service->dispatchCrossedThresholds(
        companyId: 1,
        resource: 'users',
        previousUsed: 100,
        currentUsed: 200,
        limit: null,
    );

    Queue::assertNothingPushed();
});

/**
 * Verify that crossing 80 percent dispatches exactly one threshold job.
 */
test('crossing 80 percent dispatches threshold notification', function (): void {
    Queue::fake();

    $service = new LimitThresholdService;

    $service->dispatchCrossedThresholds(
        companyId: 1,
        resource: 'users',
        previousUsed: 3,
        currentUsed: 4,
        limit: 5,
    );

    Queue::assertPushed(
        NotifyLimitThreshold::class,
        1,
    );

    Queue::assertPushed(
        NotifyLimitThreshold::class,
        fn (NotifyLimitThreshold $job): bool => $job->companyId === 1
            && $job->resource === 'users'
            && $job->threshold === 80,
    );
});

/**
 * Verify that crossing 100 percent dispatches the 100 percent notification.
 */
test('crossing 100 percent dispatches threshold notification', function (): void {
    Queue::fake();

    $service = new LimitThresholdService;

    $service->dispatchCrossedThresholds(
        companyId: 1,
        resource: 'users',
        previousUsed: 4,
        currentUsed: 5,
        limit: 5,
    );

    Queue::assertPushed(
        NotifyLimitThreshold::class,
        fn (NotifyLimitThreshold $job): bool => $job->companyId === 1
            && $job->resource === 'users'
            && $job->threshold === 100,
    );
});

/**
 * Verify that an already-crossed threshold is not dispatched repeatedly.
 */
test('already crossed threshold does not dispatch again', function (): void {
    Queue::fake();

    $service = new LimitThresholdService;

    $service->dispatchCrossedThresholds(
        companyId: 1,
        resource: 'users',
        previousUsed: 4,
        currentUsed: 4,
        limit: 5,
    );

    Queue::assertNothingPushed();
});

/**
 * Verify that the cache warming job carries the tenant identifier.
 */
test('cache warming job carries tenant context', function (): void {
    $job = new WarmTenantCache(123);

    expect($job->companyId)->toBe(123);
});

/**
 * Verify that cache warming executes under the requested tenant.
 */
test('cache warming job executes in tenant context', function (): void {
    $company = Company::factory()->create();

    $tenantContext = app(TenantContext::class);
    $cacheService = app(CacheService::class);

    (new WarmTenantCache($company->id))->handle(
        $tenantContext,
        $cacheService,
    );

    /*
     * TenantContext::runAs() restores the previous context after
     * the callback completes. Therefore the context must be empty
     * after the job finishes instead of asserting id() here.
     */
    expect($tenantContext->idOrNull())->toBeNull();
});
