<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Subscription;
use App\Models\User;

/**
 * Authorizes subscription-related actions within the user's tenant.
 *
 * Owners and admins can view subscription details.
 * All tenant roles can view usage.
 * Only the owner can upgrade the subscription.
 */
final class SubscriptionPolicy
{
    /**
     * Determine whether the user may view subscription details.
     */
    public function view(
        User $user,
        Subscription $subscription,
    ): bool {
        return $user->company_id === $subscription->company_id
            && in_array(
                $user->role,
                [
                    Role::Owner,
                    Role::Admin,
                ],
                true,
            );
    }

    /**
     * Determine whether the user may view subscription usage.
     */
    public function viewUsage(
        User $user,
        Subscription $subscription,
    ): bool {
        return $user->company_id === $subscription->company_id;
    }

    /**
     * Determine whether the user may upgrade the subscription.
     *
     * Only the company owner may change the company's plan.
     */
    public function upgrade(
        User $user,
        Subscription $subscription,
    ): bool {
        return $user->company_id === $subscription->company_id
            && $user->role === Role::Owner;
    }
}
