<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CompanyRegistrationService
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    /**
     * @param array{
     *     company_name: string,
     *     company_slug: string,
     *     name: string,
     *     email: string,
     *     password: string
     * } $data
     * @return array{company: Company, user: User, subscription: Subscription}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $company = Company::create([
                'name' => $data['company_name'],
                'slug' => Str::lower($data['company_slug']),
            ]);

            return $this->tenantContext->runAs(
                $company->id,
                function () use ($data, $company): array {
                    $freePlan = Plan::query()
                        ->active()
                        ->where('slug', 'free')
                        ->first();

                    if ($freePlan === null) {
                        throw new \RuntimeException('Free plan is not configured.');
                    }

                    $user = User::create([
                        'name' => $data['name'],
                        'email' => Str::lower($data['email']),
                        'password' => $data['password'],
                        'role' => Role::Owner,
                        'status' => UserStatus::Active,
                        'company_id' => $company->id,
                    ]);

                    $subscription = Subscription::create([
                        'company_id' => $company->id,
                        'plan_id' => $freePlan->id,
                        'status' => SubscriptionStatus::Active,
                        'starts_at' => now(),
                    ]);

                    return [
                        'company' => $company,
                        'user' => $user,
                        'subscription' => $subscription,
                    ];
                }
            );
        });
    }
}
