<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Authorizes user-management actions within the current tenant.
 *
 * The policy establishes the role hierarchy:
 * Owner > Admin > User.
 *
 * Additional business restrictions such as self-action protection and
 * owner protection are deliberately handled by the service/controller
 * layer so that stable business error codes can be returned.
 */
final class UserPolicy
{
    /**
     * All tenant roles may list users.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * All tenant roles may view an individual user.
     */
    public function view(User $user, User $target): bool
    {
        return $user->company_id === $target->company_id;
    }

    /**
     * Determine whether the user may create another user.
     */
    public function create(User $user): bool
    {
        return in_array(
            $user->role,
            [Role::Owner, Role::Admin],
            true,
        );
    }

    /**
     * Determine whether the actor may update the target.
     *
     * Owners may update non-owner users.
     * Admins may update Users only.
     */
    public function update(User $user, User $target): bool
    {
        if ($user->company_id !== $target->company_id) {
            return false;
        }

        if ($user->role === Role::Owner) {
            return $target->role !== Role::Owner;
        }

        if ($user->role === Role::Admin) {
            return $target->role === Role::User;
        }

        return false;
    }

    /**
     * Determine whether the actor may delete the target.
     *
     * Owners may delete Admin/User.
     * Admins may delete User.
     */
    public function delete(User $user, User $target): bool
    {
        if ($user->company_id !== $target->company_id) {
            return false;
        }

        if ($user->id === $target->id) {
            return false;
        }

        if ($target->role === Role::Owner) {
            return false;
        }

        return match ($user->role) {
            Role::Owner => true,
            Role::Admin => $target->role === Role::User,
            Role::User => false,
        };
    }
}
