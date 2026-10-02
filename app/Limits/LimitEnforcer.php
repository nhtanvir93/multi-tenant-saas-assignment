<?php

declare(strict_types=1);

namespace App\Limits;

use App\Enums\SubscriptionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Company;
use App\Models\Subscription;

/**
 * Coordinates all resource-specific subscription limit checks.
 *
 * Resource-specific counting is delegated to LimitCheck strategies.
 * This keeps the enforcer closed for modification when new limited
 * resources are introduced.
 */
final class LimitEnforcer
{
    /**
     * @param  iterable<LimitCheck>  $checks
     */
    public function __construct(
        private readonly iterable $checks,
    ) {}

    /**
     * Ensure that a company can create another resource.
     *
     * The caller must execute this method inside the same transaction
     * that performs the eventual resource insertion.
     *
     * @throws BusinessRuleException When the resource has reached its limit.
     */
    public function ensureCanCreate(
        Company $company,
        string $resource,
    ): void {
        $subscription = Subscription::query()
            ->where('company_id', $company->id)
            ->where('status', SubscriptionStatus::Active)
            ->lockForUpdate()
            ->with('plan')
            ->firstOrFail();

        foreach ($this->checks as $check) {
            if ($check->resource() !== $resource) {
                continue;
            }

            $used = $check->used($company->id);
            $limit = $check->limitFor($subscription->plan);

            if ($limit !== null && $used >= $limit) {
                throw new BusinessRuleException(
                    sprintf(
                        'The %s limit for the %s plan has been reached.',
                        $resource,
                        $subscription->plan->name,
                    ),
                    errorCode: 'PLAN_LIMIT_REACHED',
                    httpStatus: 403,
                    details: [
                        'resource' => $resource,
                        'used' => $used,
                        'limit' => $limit,
                        'plan' => $subscription->plan->slug,
                    ],
                );
            }

            return;
        }

        throw new \InvalidArgumentException(
            sprintf('No limit check registered for resource [%s].', $resource),
        );
    }
}
