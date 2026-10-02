<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Runs on every container start, so it must stay idempotent and only seed
     * reference data. Demo tenants will live in a separate, opt-in seeder.
     */
    public function run(): void
    {
        $this->call(PlanSeeder::class);
    }
}
