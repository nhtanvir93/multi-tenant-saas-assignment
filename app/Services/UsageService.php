<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Limits\LimitCheck;
use App\Models\Company;
use App\Models\Subscription;

/**
 * Calculates current subscription usage for a company.
 *
 * The same LimitCheck strategies used by LimitEnforcer are used here,
 * ensuring usage reporting and creation enforcement share one source
 * of truth.
 */
final class UsageService
{
    /**
     * @param  iterable<LimitCheck>  $checks
     */
    public function __construct(
        private readonly iterable $checks,
    ) {}

    /**
     * Return usage information for every registered limited resource.
     *
     * @return array<string, array{
     *     used: int,
     *     limit: int|null,
     *     percent: float|null
     * }>
     */
    public function forCompany(Company $company): array
    {
        /** @var Subscription $subscription */
        $subscription = Subscription::query()
            ->where('company_id', $company->id)
            ->where('status', SubscriptionStatus::Active)
            ->with('plan')
            ->firstOrFail();

        $usage = [];

        foreach ($this->checks as $check) {
            $used = $check->used($company->id);
            $limit = $check->limitFor($subscription->plan);

            $usage[$check->resource()] = [
                'used' => $used,
                'limit' => $limit,
                'percent' => $limit === null
                    ? null
                    : round(($used / $limit) * 100, 2),
            ];
        }

        return $usage;
    }
}
