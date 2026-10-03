<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Verify that eager loading the company relationship avoids an N+1 query.
 */
test('eager loading company does not create an n plus one query pattern', function (): void {
    $company = Company::factory()->create();

    $tenantContext = app(TenantContext::class);

    $tenantContext->runAs(
        $company->id,
        function () use ($company): void {
            User::factory()
                ->count(10)
                ->create([
                    'company_id' => $company->id,
                    'role' => Role::User,
                ]);

            DB::flushQueryLog();
            DB::enableQueryLog();

            $users = User::query()
                ->with('company')
                ->get();

            foreach ($users as $user) {
                $user->company->id;
            }

            $queries = DB::getQueryLog();

            DB::disableQueryLog();

            expect($queries)
                ->not->toBeEmpty()
                ->and(count($queries))->toBeLessThanOrEqual(2);
        },
    );
});

/**
 * Verify that accessing an eager-loaded company does not add
 * one query per returned user.
 */
test('user company access does not increase query count per row', function (): void {
    $company = Company::factory()->create();

    $tenantContext = app(TenantContext::class);

    $tenantContext->runAs(
        $company->id,
        function () use ($company): void {
            User::factory()
                ->count(5)
                ->create([
                    'company_id' => $company->id,
                    'role' => Role::User,
                ]);

            DB::flushQueryLog();
            DB::enableQueryLog();

            $users = User::query()
                ->with('company')
                ->get();

            foreach ($users as $user) {
                $user->company->name;
            }

            $queryCount = count(DB::getQueryLog());

            DB::disableQueryLog();

            expect($queryCount)->toBeLessThanOrEqual(2);
        },
    );
});
