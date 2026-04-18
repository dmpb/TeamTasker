<?php

namespace App\Repositories;

use App\Models\Task;
use App\Models\TaskDependency;

class TaskDependencyRepository
{
    public function create(Task $dependent, Task $prerequisite): TaskDependency
    {
        return TaskDependency::query()->create([
            'dependent_task_id' => $dependent->id,
            'prerequisite_task_id' => $prerequisite->id,
        ]);
    }

    public function delete(Task $dependent, Task $prerequisite): void
    {
        TaskDependency::query()
            ->where('dependent_task_id', $dependent->id)
            ->where('prerequisite_task_id', $prerequisite->id)
            ->delete();
    }

    public function exists(Task $dependent, Task $prerequisite): bool
    {
        return TaskDependency::query()
            ->where('dependent_task_id', $dependent->id)
            ->where('prerequisite_task_id', $prerequisite->id)
            ->exists();
    }
}
