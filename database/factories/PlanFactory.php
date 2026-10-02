<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'slug' => $slug,
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'tier' => fake()->unique()->numberBetween(10, 32000),
            'price_cents' => 1000,
            'max_users' => 10,
            'max_customers' => 100,
            'features' => ['api_access' => true],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
