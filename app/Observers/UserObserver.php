<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\TenantResourceChanged;
use App\Models\User;

/**
 * Dispatches cache invalidation events for user writes.
 */
final class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $this->changed($user);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        $this->changed($user);
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $this->changed($user);
    }

    /**
     * Dispatch the tenant resource change event.
     */
    private function changed(User $user): void
    {
        TenantResourceChanged::dispatch(
            $user->company_id,
            'users',
        );
    }
}
