<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;

final class CompanyPolicy
{
    /**
     * Determine whether the user may view the company.
     */
    public function view(User $user, Company $company): bool
    {
        return $user->company_id === $company->id;
    }

    /**
     * Determine whether the user may update the company.
     */
    public function update(User $user, Company $company): bool
    {
        return $user->company_id === $company->id
            && $user->role === Role::Owner;
    }
}
