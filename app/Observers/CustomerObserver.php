<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\TenantResourceChanged;
use App\Models\Customer;

/**
 * Dispatches cache invalidation events for customer writes.
 */
final class CustomerObserver
{
    /**
     * Handle the Customer "created" event.
     */
    public function created(Customer $customer): void
    {
        $this->changed($customer);
    }

    /**
     * Handle the Customer "updated" event.
     */
    public function updated(Customer $customer): void
    {
        $this->changed($customer);
    }

    /**
     * Handle the Customer "deleted" event.
     */
    public function deleted(Customer $customer): void
    {
        $this->changed($customer);
    }

    /**
     * Dispatch the tenant resource change event.
     */
    private function changed(Customer $customer): void
    {
        TenantResourceChanged::dispatch(
            $customer->company_id,
            'customers',
        );
    }
}
