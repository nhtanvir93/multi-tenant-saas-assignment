<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Global subscription plan catalogue. Not tenant-owned: it has no company_id
 * and never uses the tenant scope. NULL limits mean "unlimited".
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'tier',
        'price_cents',
        'max_users',
        'max_customers',
        'features',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tier' => 'integer',
            'price_cents' => 'integer',
            'max_users' => 'integer',
            'max_customers' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @param Builder<Plan> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<Plan> $query */
    public function scopeOrderedByTier(Builder $query): void
    {
        $query->orderBy('tier');
    }
}
