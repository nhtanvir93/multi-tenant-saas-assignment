<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Builds the tenant dashboard with Redis cache-aside and stampede protection.
 */
final class DashboardService
{
    public function __construct(
        private readonly UsageService $usageService,
        private readonly CacheService $cacheService,
    ) {}

    /**
     * Return the dashboard for the authenticated user.
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $visibility = (
            $user->role === Role::Owner
            || $user->role === Role::Admin
        )
            ? 'full'
            : 'summary';

        $lock = $this->cacheService->dashboardLock();

        return $lock->block(
            5,
            fn (): array => $this->cacheService->dashboard(
                $visibility,
                fn (): array => $this->buildDashboard($user),
            ),
        );
    }

    /**
     * Build the dashboard payload from the database.
     *
     * @return array<string, mixed>
     */
    private function buildDashboard(User $user): array
    {
        $usage = $this->usageService->forCompany(
            $user->company,
        );

        $subscription = $user->company
            ->subscriptions()
            ->with('plan')
            ->where('status', 'active')
            ->first();

        if ($subscription === null) {
            throw (new ModelNotFoundException)
                ->setModel(Subscription::class);
        }

        $data = [
            'users' => $usage['users'],
            'customers' => $usage['customers'],
            'plan' => [
                'slug' => $subscription->plan->slug,
                'name' => $subscription->plan->name,
            ],
        ];

        if (
            $user->role === Role::Owner
            || $user->role === Role::Admin
        ) {
            $data['customer_status'] = [
                'active' => Customer::query()
                    ->where('status', 'active')
                    ->count(),
                'inactive' => Customer::query()
                    ->where('status', 'inactive')
                    ->count(),
                'lead' => Customer::query()
                    ->where('status', 'lead')
                    ->count(),
            ];

            $data['users_by_role'] = [
                'owner' => User::query()
                    ->where('role', Role::Owner)
                    ->count(),
                'admin' => User::query()
                    ->where('role', Role::Admin)
                    ->count(),
                'user' => User::query()
                    ->where('role', Role::User)
                    ->count(),
            ];
        }

        return $data;
    }
}
