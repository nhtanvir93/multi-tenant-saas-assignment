<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Limits\LimitEnforcer;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Handles user creation and lifecycle changes inside a tenant.
 *
 * Authorization decisions remain in UserPolicy. This service owns
 * user-specific business rules and transactional state changes.
 */
final class UserService
{
    /**
     * Create a user after enforcing the tenant's subscription limit.
     *
     * The subscription row is locked by LimitEnforcer and the user insert
     * happens inside the same database transaction.
     *
     * @param array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     role: Role
     * } $data
     */
    public function create(
        Company $company,
        array $data,
        LimitEnforcer $limitEnforcer,
    ): User {
        if ($data['role'] === Role::Owner) {
            throw new BusinessRuleException(
                'An owner cannot be created.',
                errorCode: 'OWNER_PROTECTED',
                httpStatus: 403,
            );
        }

        return DB::transaction(function () use (
            $company,
            $data,
            $limitEnforcer,
        ): User {
            $limitEnforcer->ensureCanCreate($company, 'users');

            return User::create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'status' => UserStatus::Active,
            ]);
        });
    }

    /**
     * Update a user's editable profile fields and role/status.
     *
     * Authorization and protected-target rules are checked before this
     * method is called by the controller/policy layer.
     *
     * @param array{
     *     name?: string,
     *     email?: string,
     *     role?: Role,
     *     status?: UserStatus,
     *     password?: string
     * } $data
     */
    public function update(
        User $user,
        array $data,
    ): User {
        return DB::transaction(function () use ($user, $data): User {
            $passwordChanged = array_key_exists('password', $data);

            if (array_key_exists('name', $data)) {
                $user->name = $data['name'];
            }

            if (array_key_exists('email', $data)) {
                $user->email = Str::lower($data['email']);
            }

            if (array_key_exists('role', $data)) {
                $user->role = $data['role'];
            }

            if (array_key_exists('status', $data)) {
                $user->status = $data['status'];
            }

            if ($passwordChanged) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();

            if (
                $passwordChanged ||
                array_key_exists('role', $data) ||
                array_key_exists('status', $data)
            ) {
                $user->tokens()->delete();
            }

            return $user->refresh();
        });
    }

    /**
     * Hard-delete a user and revoke all of their access tokens.
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });
    }

    /**
     * Ensure a protected owner cannot be modified through a forbidden action.
     *
     * @throws BusinessRuleException
     */
    public function ensureOwnerCanBeChanged(
        User $target,
        string $action,
    ): void {
        if ($target->role !== Role::Owner) {
            return;
        }

        throw new BusinessRuleException(
            sprintf('The company owner cannot be %s.', $action),
            errorCode: 'OWNER_PROTECTED',
            httpStatus: 403,
        );
    }

    /**
     * Prevent a user from modifying their own role or deleting themselves.
     *
     * @throws BusinessRuleException
     */
    public function ensureNotSelfAction(
        User $actor,
        User $target,
        string $action,
    ): void {
        if ($actor->id !== $target->id) {
            return;
        }

        throw new BusinessRuleException(
            sprintf('You cannot %s yourself.', $action),
            errorCode: 'SELF_ACTION_FORBIDDEN',
            httpStatus: 403,
        );
    }
}
