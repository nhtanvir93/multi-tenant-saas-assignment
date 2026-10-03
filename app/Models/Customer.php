<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Support\Tenancy\BelongsToTenant;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents a tenant-owned customer.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property CustomerStatus $status
 * @property string|null $notes
 */
final class Customer extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the company that owns the customer.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
