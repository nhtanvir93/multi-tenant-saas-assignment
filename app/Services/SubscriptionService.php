<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Exceptions\BusinessRuleException;
use App\Jobs\WarmTenantCache;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Handles subscription lifecycle and plan changes.
 *
 * Business rules:
 * - Only upgrades are allowed.
 * - The target plan must be active.
 * - The current subscription is replaced, never deleted.
 * - The new subscription becomes active immediately.
 * - The operation is serialized with a row lock.
 */
final class SubscriptionService
{
    /**
     * Upgrade a company's active subscription to another plan.
     *
     * @throws BusinessRuleException When the target plan is unavailable,
     *                               identical to the current plan,
     *                               or represents a downgrade.
     */
    public function upgrade(
        Subscription $subscription,
        int $targetPlanId,
    ): Subscription {
        $newSubscription = DB::transaction(function () use (
            $subscription,
            $targetPlanId,
        ): Subscription {
            /** @var Subscription $current */
            $current = Subscription::query()
                ->whereKey($subscription->getKey())
                ->where('status', SubscriptionStatus::Active)
                ->lockForUpdate()
                ->with('plan')
                ->firstOrFail();

            /** @var Plan|null $targetPlan */
            $targetPlan = Plan::query()
                ->whereKey($targetPlanId)
                ->first();

            if ($targetPlan === null || ! $targetPlan->is_active) {
                throw new BusinessRuleException(
                    'The selected plan is not available.',
                    errorCode: 'PLAN_NOT_AVAILABLE',
                    httpStatus: 422,
                );
            }

            /** @var Plan $currentPlan */
            $currentPlan = $current->plan;

            if ($targetPlan->id === $currentPlan->id) {
                throw new BusinessRuleException(
                    'The company is already on this plan.',
                    errorCode: 'ALREADY_ON_PLAN',
                    httpStatus: 422,
                );
            }

            if ($targetPlan->tier <= $currentPlan->tier) {
                throw new BusinessRuleException(
                    'Downgrading the subscription is not allowed.',
                    errorCode: 'DOWNGRADE_NOT_ALLOWED',
                    httpStatus: 422,
                );
            }

            $current->update([
                'status' => SubscriptionStatus::Replaced,
                'ends_at' => now(),
            ]);

            $newSubscription = Subscription::create([
                'company_id' => $current->company_id,
                'plan_id' => $targetPlan->id,
                'status' => SubscriptionStatus::Active,
                'starts_at' => now(),
            ]);

            return $newSubscription->load('plan');
        });

        WarmTenantCache::dispatch($newSubscription->company_id);

        return $newSubscription;
    }
}
