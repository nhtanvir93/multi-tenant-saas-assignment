<?php

declare(strict_types=1);

namespace App\Limits;

use App\Models\Plan;
use App\Models\User;

/**
 * Enforces and reports the user-seat limit.
 *
 * All users, including inactive users, occupy a seat.
 * Users are hard-deleted, so COUNT(*) represents the authoritative usage.
 */
final class UserLimitCheck implements LimitCheck
{
    /**
     * {@inheritDoc}
     */
    public function resource(): string
    {
        return 'users';
    }

    /**
     * {@inheritDoc}
     */
    public function used(int $companyId): int
    {
        return User::query()
            ->where('company_id', $companyId)
            ->count();
    }

    /**
     * {@inheritDoc}
     */
    public function limitFor(Plan $plan): ?int
    {
        return $plan->max_users;
    }
}
