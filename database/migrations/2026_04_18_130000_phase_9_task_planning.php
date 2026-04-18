<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('description');
            $table->string('priority', 20)->default('medium')->after('due_date');
            $table->timestamp('completed_at')->nullable()->after('priority');
            $table->index(['project_id', 'due_date']);
            $table->index(['project_id', 'priority']);
            $table->index(['project_id', 'completed_at']);
        });

        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 32)->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'name']);
        });

        Schema::create('label_task', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['task_id', 'label_id']);
        });

        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['task_id', 'position']);
        });

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependent_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('prerequisite_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['dependent_task_id', 'prerequisite_task_id']);
            $table->index('prerequisite_task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('task_checklist_items');
        Schema::dropIfExists('label_task');
        Schema::dropIfExists('labels');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'due_date']);
            $table->dropIndex(['project_id', 'priority']);
            $table->dropIndex(['project_id', 'completed_at']);
            $table->dropColumn(['due_date', 'priority', 'completed_at']);
        });
    }
};
