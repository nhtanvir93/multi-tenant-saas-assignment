<?php

declare(strict_types=1);

namespace App\Limits;

use App\Models\Customer;
use App\Models\Plan;

/**
 * Checks customer usage against the active subscription plan.
 */
final class CustomerLimitCheck implements LimitCheck
{
    /**
     * Return the limited resource name.
     */
    public function resource(): string
    {
        return 'customers';
    }

    /**
     * Count non-deleted customers for the company.
     */
    public function used(int $companyId): int
    {
        return Customer::query()
            ->where('company_id', $companyId)
            ->count();
    }

    /**
     * Return the maximum customer count allowed by the plan.
     */
    public function limitFor(Plan $plan): ?int
    {
        return $plan->max_customers;
    }
}
