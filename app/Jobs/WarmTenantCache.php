<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Company;
use App\Services\CacheService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Warms the frequently accessed tenant cache entries.
 */
final class WarmTenantCache implements ShouldQueue
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
     * Create a new cache warming job.
     */
    public function __construct(
        public readonly int $companyId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        TenantContext $tenantContext,
        CacheService $cacheService,
    ): void {
        $company = Company::query()->findOrFail($this->companyId);

        $tenantContext->runAs(
            $company->id,
            function () use ($cacheService): void {
                $cacheService->subscription(
                    fn (): mixed => null,
                );

                $cacheService->usage(
                    fn (): mixed => null,
                );

                $cacheService->dashboard(
                    'full',
                    fn (): mixed => null,
                );
            },
        );
    }
}
