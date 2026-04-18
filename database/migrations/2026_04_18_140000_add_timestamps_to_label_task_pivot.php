<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('label_task')) {
            return;
        }

        if (Schema::hasColumn('label_task', 'created_at')) {
            return;
        }

        Schema::table('label_task', function (Blueprint $table): void {
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('label_task')) {
            return;
        }

        if (! Schema::hasColumn('label_task', 'created_at')) {
            return;
        }

        Schema::table('label_task', function (Blueprint $table): void {
            $table->dropTimestamps();
        });
    }
};
