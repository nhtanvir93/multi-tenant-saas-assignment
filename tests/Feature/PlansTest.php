<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlansTest extends TestCase
{
    use RefreshDatabase;

    public function test_plans_endpoint_is_public_and_lists_active_plans_ordered_by_tier(): void
    {
        $this->seed(PlanSeeder::class);

        $this->getJson('/api/v1/plans')   // no token on purpose
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.slug', 'free')
            ->assertJsonPath('data.1.slug', 'pro')
            ->assertJsonPath('data.2.slug', 'enterprise');
    }

    public function test_plan_payload_exposes_limits_features_and_null_for_unlimited(): void
    {
        $this->seed(PlanSeeder::class);

        $response = $this->getJson('/api/v1/plans')->assertOk();

        $response->assertJsonStructure([
            'data' => [['id', 'slug', 'name', 'tier', 'price_cents', 'limits' => ['users', 'customers'], 'features']],
        ]);
        $response->assertJsonPath('data.0.limits', ['users' => 5, 'customers' => 100]);
        $response->assertJsonPath('data.1.limits', ['users' => 50, 'customers' => 5000]);
        $response->assertJsonPath('data.2.limits', ['users' => null, 'customers' => null]);
        $response->assertJsonPath('data.1.features.csv_export', true);
    }

    public function test_inactive_plans_are_hidden(): void
    {
        $this->seed(PlanSeeder::class);
        Plan::factory()->inactive()->create(['slug' => 'legacy', 'tier' => 99]);

        $this->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonMissing(['slug' => 'legacy']);
    }

    public function test_seeder_is_idempotent_and_restores_catalogue_values(): void
    {
        $this->seed(PlanSeeder::class);
        Plan::query()->where('slug', 'pro')->update(['max_users' => 1]);

        $this->seed(PlanSeeder::class);

        $this->assertSame(3, Plan::query()->count());
        $this->assertSame(50, Plan::query()->where('slug', 'pro')->value('max_users'));
    }

    public function test_database_rejects_non_positive_limits(): void
    {
        $this->expectException(QueryException::class);

        Plan::factory()->create(['max_users' => 0]);
    }

    public function test_database_rejects_negative_price(): void
    {
        $this->expectException(QueryException::class);

        Plan::factory()->create(['price_cents' => -1]);
    }

    public function test_database_rejects_duplicate_tier(): void
    {
        Plan::factory()->create(['tier' => 50]);

        $this->expectException(QueryException::class);

        Plan::factory()->create(['tier' => 50]);
    }
}
