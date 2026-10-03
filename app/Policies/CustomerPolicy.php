<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;

/**
 * Defines authorization rules for customer operations.
 */
final class CustomerPolicy
{
    /**
     * Determine whether the user may list customers.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user may view a customer.
     */
    public function view(User $user, Customer $customer): bool
    {
        return $user->company_id === $customer->company_id;
    }

    /**
     * Determine whether the user may create a customer.
     */
    public function create(User $user): bool
    {
        return in_array(
            $user->role,
            [Role::Owner, Role::Admin, Role::User],
            true,
        );
    }

    /**
     * Determine whether the user may update a customer.
     */
    public function update(User $user, Customer $customer): bool
    {
        return $user->company_id === $customer->company_id
            && in_array(
                $user->role,
                [Role::Owner, Role::Admin, Role::User],
                true,
            );
    }

    /**
     * Determine whether the user may delete a customer.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->company_id === $customer->company_id
            && in_array(
                $user->role,
                [Role::Owner, Role::Admin],
                true,
            );
    }
}
