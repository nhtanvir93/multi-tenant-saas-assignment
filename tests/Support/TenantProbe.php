<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Test-only tenant model; the real ones arrive in steps 6, 9 and 10. */
final class TenantProbe extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'tenant_probes';

    protected $fillable = ['name'];

    protected $casts = ['company_id' => 'integer'];
}
