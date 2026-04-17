<?php

namespace App\Services;

use App\Models\Column;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskRepository;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class TaskService
{
    public function __construct(
        public TaskRepository $taskRepository,
        public ActivityLogService $activityLogService,
    ) {}

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
        ?User $actor = null,
        ?int $position = null,
    ): Task {
        $task = $this->taskRepository->createTask($project, $column, $title, $description, $assignee, $position);
        $this->activityLogService->recordTaskCreated($task, $actor);

        return $task;
    }

    public function updateTask(
        Project $project,
        Task $task,
        string $title,
        ?string $description = null,
        ?User $assignee = null,
        ?User $actor = null,
    ): Task {
        $this->assertTaskBelongsToProject($task, $project);

        $updatedTask = $this->taskRepository->updateTask($task, $title, $description, $assignee);
        $this->activityLogService->recordTaskUpdated($updatedTask, $actor);

        return $updatedTask;
    }

    public function moveTaskToColumn(Project $project, Task $task, Column $targetColumn, ?User $actor = null): Task
    {
        $this->assertTaskBelongsToProject($task, $project);

        $fromColumnId = $task->column_id;
        $movedTask = $this->taskRepository->moveTaskToColumn($task, $targetColumn);

        if ($fromColumnId !== $movedTask->column_id) {
            $this->activityLogService->recordTaskMoved($movedTask, $fromColumnId, $movedTask->column_id, $actor);
        }

        return $movedTask;
    }

    public function deleteTask(Project $project, Task $task, ?User $actor = null): void
    {
        $this->assertTaskBelongsToProject($task, $project);

        $this->activityLogService->recordTaskDeleted($task, $actor);
        $this->taskRepository->deleteTask($task);
    }

    private function assertTaskBelongsToProject(Task $task, Project $project): void
    {
        if ($task->project_id !== $project->id) {
            throw new InvalidArgumentException('Task does not belong to the given project.');
        }
    }
}
