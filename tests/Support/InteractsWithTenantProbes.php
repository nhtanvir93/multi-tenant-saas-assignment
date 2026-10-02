<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Creates a throwaway tenant table (rolled back with the test transaction) and helpers. */
trait InteractsWithTenantProbes
{
    public function createProbeTable(): void
    {
        Schema::create('tenant_probes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name');
        });
    }

    public function asTenant(int $companyId, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAs($companyId, $callback);
    }

    public function probe(int $companyId, string $name): TenantProbe
    {
        return $this->asTenant($companyId, static fn (): TenantProbe => TenantProbe::create(['name' => $name]));
    }

    public function tenantUser(int $companyId): GenericUser
    {
        return new GenericUser(['id' => 1, 'company_id' => $companyId]);
    }
}
