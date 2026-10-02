<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the tenant from the AUTHENTICATED user's company_id, never from request input.
 * Must run after auth:sanctum and before SubstituteBindings (priority is registered
 * in AppServiceProvider). The context is restored when the request ends.
 */
final class SetTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $companyId = $request->user()?->company_id;

        if ($companyId === null) {
            throw new AuthenticationException;
        }

        return $this->context->runAs((int) $companyId, static fn (): Response => $next($request));
    }
}
