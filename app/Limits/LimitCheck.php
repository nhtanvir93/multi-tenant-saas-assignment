<?php

declare(strict_types=1);

namespace App\Limits;

use App\Models\Plan;

/**
 * Contract for a resource-specific subscription limit check.
 *
 * Implementations provide both authoritative usage counts and the
 * corresponding limit from the active subscription plan.
 */
interface LimitCheck
{
    /**
     * Get the resource name represented by this check.
     *
     * @return string The resource key, for example "users".
     */
    public function resource(): string;

    /**
     * Get the authoritative current usage for a company.
     *
     * @param  int  $companyId  Tenant company identifier.
     */
    public function used(int $companyId): int;

    /**
     * Get the maximum allowed usage for the given plan.
     *
     * A null result means that the resource is unlimited.
     *
     * @return int|null Maximum allowed resource count, or null for unlimited.
     */
    public function limitFor(Plan $plan): ?int;
}
