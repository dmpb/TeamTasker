<?php

namespace App\Services;

use App\Models\Column;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskRepository;
use Illuminate\Database\Eloquent\Collection;

class TaskService
{
    public function __construct(public TaskRepository $taskRepository) {}

    /**
     * Columns with `tasks` eager-loaded and ordered (avoids N+1 on the board).
     *
     * @return Collection<int, Column>
     */
    public function boardColumnsWithTasks(Project $project): Collection
    {
        return $this->taskRepository->listColumnsWithTasksOrderedForProject($project);
    }

    /**
     * @return Collection<int, Task>
     */
    public function listColumnTasks(Column $column): Collection
    {
        return $this->taskRepository->listTasksForColumnOrdered($column);
    }

    public function createTask(
        Project $project,
        Column $column,
        string $title,
        ?string $description = null,
        ?User $assignee = null,
        ?int $position = null,
    ): Task {
        return $this->taskRepository->createTask($project, $column, $title, $description, $assignee, $position);
    }

    public function updateTask(
        Task $task,
        string $title,
        ?string $description = null,
        ?User $assignee = null,
    ): Task {
        return $this->taskRepository->updateTask($task, $title, $description, $assignee);
    }

    public function moveTaskToColumn(Task $task, Column $targetColumn): Task
    {
        return $this->taskRepository->moveTaskToColumn($task, $targetColumn);
    }

    public function deleteTask(Task $task): void
    {
        $this->taskRepository->deleteTask($task);
    }
}
