<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('board_columns', function (Blueprint $table): void {
            $table->unique(['id', 'project_id'], 'board_columns_id_project_unique');
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropForeign(['column_id']);

            $table->foreign(['column_id', 'project_id'], 'tasks_column_project_foreign')
                ->references(['id', 'project_id'])
                ->on('board_columns')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropForeign('tasks_column_project_foreign');

            $table->foreign('column_id')
                ->references('id')
                ->on('board_columns')
                ->cascadeOnDelete();
        });

        Schema::table('board_columns', function (Blueprint $table): void {
            $table->dropUnique('board_columns_id_project_unique');
        });
    }
};
