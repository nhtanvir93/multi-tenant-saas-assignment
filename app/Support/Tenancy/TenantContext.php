<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Exceptions\TenantContextMissingException;
use Closure;

/**
 * Holds the active tenant (company id) for the current request or job.
 *
 * Bound as a scoped singleton (see AppServiceProvider): one instance per request,
 * reset between queued jobs. There is deliberately no public setter; a tenant can
 * only be entered through runAs(), which always restores the previous state, so the
 * context cannot leak from one unit of work into the next.
 */
final class TenantContext
{
    private ?int $companyId = null;

    /** @throws TenantContextMissingException when no tenant is active (fail closed) */
    public function id(): int
    {
        return $this->companyId ?? throw new TenantContextMissingException;
    }

    public function idOrNull(): ?int
    {
        return $this->companyId;
    }

    public function has(): bool
    {
        return $this->companyId !== null;
    }

    /**
     * Run $callback as the given tenant, then restore whatever was active before.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runAs(int $companyId, Closure $callback): mixed
    {
        $previous = $this->companyId;
        $this->companyId = $companyId;

        try {
            return $callback();
        } finally {
            $this->companyId = $previous;
        }
    }
}
