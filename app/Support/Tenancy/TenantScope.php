<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Adds `WHERE <table>.company_id = <active tenant>` to every query of a tenant model.
 * Resolving the id throws when no tenant is active, so a forgotten context fails
 * closed instead of returning all rows.
 *
 * @implements Scope<Model>
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('company_id'), app(TenantContext::class)->id());
    }
}
