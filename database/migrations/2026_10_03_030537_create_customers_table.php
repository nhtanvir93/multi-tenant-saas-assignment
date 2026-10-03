<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('email', 255);
            $table->string('phone', 30)->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'created_at']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX customers_company_email_unique '
            .'ON customers (company_id, LOWER(email)) '
            .'WHERE deleted_at IS NULL'
        );

        DB::statement(
            'CREATE INDEX customers_company_created_live_idx '
            .'ON customers (company_id, created_at DESC, id DESC) '
            .'WHERE deleted_at IS NULL'
        );

        DB::statement(
            'CREATE INDEX customers_company_status_created_live_idx '
            .'ON customers (company_id, status, created_at DESC, id DESC) '
            .'WHERE deleted_at IS NULL'
        );

        DB::statement(
            'ALTER TABLE customers ADD CONSTRAINT customers_status_check '
            ."CHECK (status IN ('active', 'inactive', 'lead'))"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
