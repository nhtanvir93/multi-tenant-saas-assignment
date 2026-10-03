<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\NotifyLimitThreshold;

/**
 * Dispatches asynchronous notifications when a tenant crosses
 * a configured usage threshold.
 */
final class LimitThresholdService
{
    /**
     * Threshold percentages supported by the application.
     *
     * @var list<int>
     */
    private const THRESHOLDS = [80, 100];

    /**
     * Dispatch notifications for thresholds crossed by a resource.
     *
     * A notification is dispatched only when the resource moves from
     * below a threshold to at least that threshold.
     *
     * @param  int  $companyId  Tenant identifier.
     * @param  string  $resource  Resource name, such as "users" or "customers".
     * @param  int  $previousUsed  Usage before the write.
     * @param  int  $currentUsed  Usage after the write.
     * @param  int|null  $limit  Current plan limit.
     */
    public function dispatchCrossedThresholds(
        int $companyId,
        string $resource,
        int $previousUsed,
        int $currentUsed,
        ?int $limit,
    ): void {
        if ($limit === null || $limit <= 0) {
            return;
        }

        foreach (self::THRESHOLDS as $threshold) {
            $previousPercent = ($previousUsed / $limit) * 100;
            $currentPercent = ($currentUsed / $limit) * 100;

            if (
                $previousPercent < $threshold
                && $currentPercent >= $threshold
            ) {
                NotifyLimitThreshold::dispatch(
                    $companyId,
                    $resource,
                    $threshold,
                );
            }
        }
    }
}
