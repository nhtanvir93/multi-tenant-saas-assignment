<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

/**
 * Verify that an authenticated API user can make up to sixty requests per minute.
 */
test('authenticated api allows sixty requests per minute', function (): void {
    $company = Company::factory()->create();

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => Role::User,
    ]);

    Sanctum::actingAs($user);

    RateLimiter::clear('api|user:'.$user->getAuthIdentifier());

    for ($attempt = 1; $attempt <= 60; $attempt++) {
        $response = $this->getJson('/api/v1/auth/me');

        expect($response->status())->not->toBe(429);
    }
});

/**
 * Verify that the sixty-first authenticated API request is rejected.
 */
test('authenticated api rejects the sixty-first request within one minute', function (): void {
    $company = Company::factory()->create();

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => Role::User,
    ]);

    Sanctum::actingAs($user);

    RateLimiter::clear('api|user:'.$user->getAuthIdentifier());

    for ($attempt = 1; $attempt <= 60; $attempt++) {
        $this->getJson('/api/v1/auth/me');
    }

    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(429);
});

/**
 * Verify that authenticated API rate limits are isolated between users.
 */
test('authenticated api rate limit is isolated between users', function (): void {
    $company = Company::factory()->create();

    $firstUser = User::factory()->create([
        'company_id' => $company->id,
        'role' => Role::User,
    ]);

    $secondUser = User::factory()->create([
        'company_id' => $company->id,
        'role' => Role::User,
    ]);

    Sanctum::actingAs($firstUser);

    RateLimiter::clear('api|user:'.$firstUser->getAuthIdentifier());

    for ($attempt = 1; $attempt <= 60; $attempt++) {
        $this->getJson('/api/v1/auth/me');
    }

    Sanctum::actingAs($secondUser);

    RateLimiter::clear('api|user:'.$secondUser->getAuthIdentifier());

    $response = $this->getJson('/api/v1/auth/me');

    $response->assertStatus(200);
});
