<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read access to the plan catalogue. Step 12 adds Redis caching here
 * (key `plans:all`) without touching the controller.
 */
class PlanService
{
    /** @return Collection<int, Plan> */
    public function activePlans(): Collection
    {
        return Plan::query()->active()->orderedByTier()->get();
    }
}
