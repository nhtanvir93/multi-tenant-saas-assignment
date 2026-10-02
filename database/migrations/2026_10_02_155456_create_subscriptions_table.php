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
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('plan_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('status', 20);

            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'starts_at']);
            $table->index('plan_id');
        });

        DB::statement(
            "CREATE UNIQUE INDEX subscriptions_one_active_per_company
             ON subscriptions (company_id)
             WHERE status = 'active'"
        );

        DB::statement(
            "ALTER TABLE subscriptions
             ADD CONSTRAINT subscriptions_status_check
             CHECK (status IN ('active', 'replaced', 'expired'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
