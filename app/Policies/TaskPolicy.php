<?php

namespace App\Policies;

use App\Models\Column;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * View a task (and list its comments) when the user can access the project.
     */
    public function view(User $user, Task $task): bool
    {
        return $user->can('view', $task->project);
    }

    /**
     * Create a task in a column (same privilege as editing the project board).
     */
    public function create(User $user, Column $column): bool
    {
        return $column->project->archived_at === null
            && $user->can('update', $column->project);
    }

    public function update(User $user, Task $task): bool
    {
        return $task->project->archived_at === null
            && $user->can('update', $task->project);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }
}
