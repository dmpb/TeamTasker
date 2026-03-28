<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforce allowed membership roles at the database layer (PostgreSQL / MySQL).
     * SQLite (typical for local tests) omits this constraint; the enum cast remains the source of truth in PHP.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql' || $driver === 'mysql') {
            DB::statement(
                "ALTER TABLE team_members ADD CONSTRAINT team_members_role_check CHECK (role IN ('owner', 'admin', 'member'))",
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE team_members DROP CONSTRAINT IF EXISTS team_members_role_check');
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE team_members DROP CHECK team_members_role_check');
        }
    }
};
