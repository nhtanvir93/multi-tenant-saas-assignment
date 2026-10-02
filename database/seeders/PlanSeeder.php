<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Idempotent (updateOrCreate by slug): runs on every container start and
     * brings the catalogue back to the values below. NULL limit = unlimited
     * (Enterprise limits are an assumption, see docs/PLAN.md 5a).
     */
    public function run(): void
    {
        foreach (self::catalogue() as $plan) {
            Plan::query()->updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }

    /** @return list<array<string, mixed>> */
    public static function catalogue(): array
    {
        return [
            [
                'slug' => 'free',
                'name' => 'Free',
                'tier' => 1,
                'price_cents' => 0,
                'max_users' => 5,
                'max_customers' => 100,
                'features' => ['api_access' => true, 'csv_export' => false, 'priority_support' => false],
                'is_active' => true,
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'tier' => 2,
                'price_cents' => 4900,
                'max_users' => 50,
                'max_customers' => 5000,
                'features' => ['api_access' => true, 'csv_export' => true, 'priority_support' => false],
                'is_active' => true,
            ],
            [
                'slug' => 'enterprise',
                'name' => 'Enterprise',
                'tier' => 3,
                'price_cents' => 19900,
                'max_users' => null,
                'max_customers' => null,
                'features' => ['api_access' => true, 'csv_export' => true, 'priority_support' => true],
                'is_active' => true,
            ],
        ];
    }
}
