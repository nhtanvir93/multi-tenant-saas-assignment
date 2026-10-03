<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;

/**
 * Verify that accidental lazy loading is prevented outside production.
 */
test('lazy loading is prevented outside production', function (): void {
    $company = Company::factory()->create();

    $tenantContext = app(TenantContext::class);

    $tenantContext->runAs(
        $company->id,
        function () use ($company): void {
            User::factory()
                ->count(2)
                ->create([
                    'company_id' => $company->id,
                    'role' => Role::User,
                ]);

            $users = User::query()
                ->where('company_id', $company->id)
                ->get();

            expect(Model::preventsLazyLoading())->toBeTrue()
                ->and($users)->toHaveCount(2);

            foreach ($users as $user) {
                expect($user->preventsLazyLoading)->toBeTrue()
                    ->and($user->relationLoaded('company'))->toBeFalse();

                expect(fn (): mixed => $user->company)
                    ->toThrow(LazyLoadingViolationException::class);
            }
        },
    );
});

/**
 * Verify that explicitly eager-loaded relationships can be accessed safely.
 */
test('eager loaded relationships can be accessed without lazy loading', function (): void {
    $company = Company::factory()->create();

    $tenantContext = app(TenantContext::class);

    $tenantContext->runAs(
        $company->id,
        function () use ($company): void {
            $user = User::factory()->create([
                'company_id' => $company->id,
                'role' => Role::User,
            ]);

            $user->load('company');

            expect($user->relationLoaded('company'))->toBeTrue()
                ->and($user->company->is($company))->toBeTrue();
        },
    );
});
