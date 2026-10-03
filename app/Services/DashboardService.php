<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;

/**
 * Builds the tenant dashboard analytics payload.
 *
 * Dashboard data is intentionally DB-backed in this step.
 * Redis caching is introduced in Step 12.
 */
final class DashboardService
{
    public function __construct(
        private readonly UsageService $usageService,
    ) {}

    /**
     * Build the dashboard for the authenticated tenant user.
     *
     * Owners and admins receive the full analytics payload.
     * Regular users receive only the summary usage information.
     *
     * @return array{
     *     users: array{
     *         used: int,
     *         limit: int|null,
     *         percent: float|null
     *     },
     *     customers: array{
     *         used: int,
     *         limit: int|null,
     *         percent: float|null
     *     },
     *     plan: array{
     *         slug: string,
     *         name: string
     *     },
     *     customer_status?: array{
     *         active: int,
     *         inactive: int,
     *         lead: int
     *     },
     *     users_by_role?: array{
     *         owner: int,
     *         admin: int,
     *         user: int
     *     }
     * }
     */
    public function forUser(User $user): array
    {
        $usage = $this->usageService->forCompany($user->company);

        $subscription = $user->company
            ->subscriptions()
            ->with('plan')
            ->where('status', 'active')
            ->firstOrFail();

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
