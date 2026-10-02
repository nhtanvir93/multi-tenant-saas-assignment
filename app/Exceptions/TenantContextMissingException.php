<?php

declare(strict_types=1);

namespace App\Exceptions;

use LogicException;

/**
 * A tenant-owned model was touched while no tenant is active.
 *
 * This is a programming error (not a business rule), so it extends LogicException
 * and the central handler renders it as a generic 500. Failing closed is the point:
 * the alternative would be silently returning every tenant's rows.
 */
final class TenantContextMissingException extends LogicException
{
    public function __construct()
    {
        parent::__construct(
            'Tenant context is not set. Use an authenticated request (SetTenantContext), '
            .'TenantContext::runAs(), or Model::withoutTenancy() explicitly.'
        );
    }
}
