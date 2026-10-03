<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Company;
use App\Models\User;
use App\Services\UsageService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Notifies a tenant when a resource usage threshold is crossed.
 */
final class NotifyLimitThreshold implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Maximum number of attempts.
     */
    public int $tries = 3;

    /**
     * Retry delays in seconds.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    /**
     * Create a new threshold notification job.
     */
    public function __construct(
        public readonly int $companyId,
        public readonly string $resource,
        public readonly int $threshold,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        TenantContext $tenantContext,
        UsageService $usageService,
    ): void {
        $company = Company::query()->findOrFail($this->companyId);

        $tenantContext->runAs(
            $company->id,
            function () use ($usageService, $company): void {
                $usage = $usageService->forCompany($company);

                $resourceUsage = $usage[$this->resource] ?? null;

                if (! is_array($resourceUsage)) {
                    return;
                }

                $percent = $resourceUsage['percent'] ?? null;

                if ($percent === null || $percent < $this->threshold) {
                    return;
                }

                $owner = User::query()
                    ->where('role', 'owner')
                    ->first();

                if ($owner === null) {
                    return;
                }

                Mail::raw(
                    sprintf(
                        'Your %s usage has reached %d%% of the current plan limit.',
                        $this->resource,
                        $this->threshold,
                    ),
                    function ($message) use ($owner): void {
                        $message
                            ->to($owner->email)
                            ->subject('Plan usage threshold reached');
                    },
                );
            },
        );
    }
}
