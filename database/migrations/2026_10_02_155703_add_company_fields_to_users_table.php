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
        // Existing email UNIQUE constraint is case-sensitive.
        // Remove it because we will replace it with LOWER(email) uniqueness.
        DB::statement(
            'ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_unique'
        );

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('role', 20);
            $table->string('status', 20)->default('active');

            $table->index(['company_id', 'role']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'created_at', 'id']);
        });

        // Case-insensitive global email uniqueness.
        DB::statement(
            'CREATE UNIQUE INDEX users_email_unique_lower
             ON users (LOWER(email))'
        );

        // Exactly one owner per company.
        DB::statement(
            "CREATE UNIQUE INDEX users_one_owner_per_company
             ON users (company_id)
             WHERE role = 'owner'"
        );

        DB::statement(
            "ALTER TABLE users
             ADD CONSTRAINT users_role_check
             CHECK (role IN ('owner', 'admin', 'user'))"
        );

        DB::statement(
            "ALTER TABLE users
             ADD CONSTRAINT users_status_check
             CHECK (status IN ('active', 'inactive'))"
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check'
        );

        DB::statement(
            'ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check'
        );

        DB::statement(
            'DROP INDEX IF EXISTS users_one_owner_per_company'
        );

        DB::statement(
            'DROP INDEX IF EXISTS users_email_unique_lower'
        );

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'created_at', 'id']);
            $table->dropIndex(['company_id', 'status']);
            $table->dropIndex(['company_id', 'role']);

            $table->dropForeign(['company_id']);

            $table->dropColumn([
                'company_id',
                'role',
                'status',
            ]);
        });

        // Restore the original migration's email uniqueness.
        DB::statement(
            'CREATE UNIQUE INDEX users_email_unique ON users (email)'
        );
    }
};
