<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\TenantResourceChanged;
use App\Models\Subscription;

/**
 * Dispatches cache invalidation events for subscription writes.
 */
final class SubscriptionObserver
{
    /**
     * Handle the Subscription "created" event.
     */
    public function created(Subscription $subscription): void
    {
        $this->changed($subscription);
    }

    /**
     * Handle the Subscription "updated" event.
     */
    public function updated(Subscription $subscription): void
    {
        $this->changed($subscription);
    }

    /**
     * Dispatch the tenant resource change event.
     */
    private function changed(Subscription $subscription): void
    {
        TenantResourceChanged::dispatch(
            $subscription->company_id,
            'subscription',
        );
    }
}
