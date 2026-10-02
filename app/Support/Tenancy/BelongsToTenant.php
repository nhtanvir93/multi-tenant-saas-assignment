<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Exceptions\TenantMismatchException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Put this trait on every model whose table has a `company_id` column.
 *
 *  - reads:   TenantScope filters by the active tenant
 *  - creates: company_id is filled from the active tenant (never from input;
 *             it must NOT be in $fillable)
 *  - updates: moving a row to another tenant is rejected
 *
 * Route model binding goes through the scope, so another tenant's id is a 404.
 * Console/seeders/tests use TenantContext::runAs() or Model::withoutTenancy().
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $tenantId = app(TenantContext::class)->id();
            $given = $model->getAttribute('company_id');

            if ($given !== null && (int) $given !== $tenantId) {
                throw new TenantMismatchException(
                    'Refusing to create a row for another tenant than the active one.'
                );
            }

            $model->setAttribute('company_id', $tenantId);
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('company_id')) {
                throw new TenantMismatchException('company_id is immutable.');
            }
        });
    }

    /** Explicit, greppable opt-out of tenant filtering (auth lookups, admin tooling). */
    public static function withoutTenancy(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }
}
