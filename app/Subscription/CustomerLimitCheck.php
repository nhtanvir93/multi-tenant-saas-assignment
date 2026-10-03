<?php

declare(strict_types=1);

namespace App\Subscription;

use App\Limits\LimitCheck;
use App\Models\Customer;
use App\Models\Plan;

/**
 * Checks customer usage against the active subscription plan limit.
 */
final class CustomerLimitCheck implements LimitCheck
{
    /**
     * Return the resource identifier handled by this check.
     */
    public function resource(): string
    {
        return 'customers';
    }

    /**
     * Count the currently active customers for a company.
     */
    public function used(int $companyId): int
    {
        return Customer::query()
            ->where('company_id', $companyId)
            ->count();
    }

    /**
     * Return the maximum number of customers allowed by the plan.
     */
    public function limitFor(Plan $plan): ?int
    {
        return $plan->max_customers;
    }
}
