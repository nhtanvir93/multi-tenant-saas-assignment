<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('tier')->unique();        // upgrade order: 1 free, 2 pro, 3 enterprise
            $table->unsignedInteger('price_cents')->default(0);
            $table->unsignedInteger('max_users')->nullable();      // NULL = unlimited
            $table->unsignedInteger('max_customers')->nullable();  // NULL = unlimited
            $table->jsonb('features')->default('{}');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // The query builder cannot express CHECK constraints (see docs/DATABASE.md section 6)
        DB::statement('ALTER TABLE plans ADD CONSTRAINT plans_tier_positive CHECK (tier > 0)');
        DB::statement('ALTER TABLE plans ADD CONSTRAINT plans_price_cents_non_negative CHECK (price_cents >= 0)');
        DB::statement('ALTER TABLE plans ADD CONSTRAINT plans_max_users_positive CHECK (max_users IS NULL OR max_users > 0)');
        DB::statement('ALTER TABLE plans ADD CONSTRAINT plans_max_customers_positive CHECK (max_customers IS NULL OR max_customers > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
