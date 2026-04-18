<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Models\Column;
use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class TaskService
{
    public function __construct(
        public TaskRepository $taskRepository,
        public ActivityLogService $activityLogService,
        public NotificationService $notificationService,
    ) {}

    /**
     * Columns with `tasks` eager-loaded and ordered (avoids N+1 on the board).
     *
     * @return Collection<int, Column>
     */
    public function boardColumnsWithTasks(
        Project $project,
        ?int $columnId = null,
        ?int $assigneeId = null,
        ?string $titleQuery = null,
        ?int $labelId = null,
        ?string $priority = null,
        ?string $duePreset = null,
    ): Collection {
        return $this->taskRepository->listColumnsWithTasksOrderedForProject(
            $project,
            $columnId,
            $assigneeId,
            $titleQuery,
            $labelId,
            $priority,
            $duePreset,
        );
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
        ?Carbon $dueDate = null,
        ?TaskPriority $priority = null,
        ?array $labelIds = null,
    ): Task {
        $task = $this->taskRepository->createTask(
            $project,
            $column,
            $title,
            $description,
            $assignee,
            $position,
            $dueDate,
            $priority,
        );

        $this->activityLogService->recordTaskCreated($task, $actor);

        if ($labelIds !== null && $labelIds !== []) {
            $this->syncTaskLabels($project, $task, $labelIds);
        }

        $fresh = $task->fresh(['labels']);

        if ($fresh->assignee_id !== null && (int) $fresh->assignee_id !== (int) ($actor?->id)) {
            $this->notificationService->notifyTaskAssigned($fresh, $actor);
        }

        return $fresh;
    }

    public function updateTask(
        Project $project,
        Task $task,
        string $title,
        ?string $description = null,
        ?User $assignee = null,
        ?User $actor = null,
        ?Carbon $dueDate = null,
        bool $clearDueDate = false,
        ?TaskPriority $priority = null,
        ?array $labelIds = null,
    ): Task {
        $this->assertTaskBelongsToProject($task, $project);

        $previousAssigneeId = $task->assignee_id;

        $extra = [];

        if ($clearDueDate) {
            $extra['due_date'] = null;
        } elseif ($dueDate !== null) {
            $extra['due_date'] = $dueDate->format('Y-m-d');
        }

        if ($priority !== null) {
            $extra['priority'] = $priority->value;
        }

        $updatedTask = $this->taskRepository->updateTask(
            $task,
            $title,
            $description,
            $assignee,
            $extra,
        );

        if ($labelIds !== null) {
            $this->syncTaskLabels($project, $updatedTask, $labelIds);
        }

        $fresh = $updatedTask->fresh(['labels']);

        $this->activityLogService->recordTaskUpdated($fresh, $actor);

        if ($fresh->assignee_id !== null && (int) $fresh->assignee_id !== (int) ($actor?->id)) {
            if ((int) ($previousAssigneeId ?? 0) !== (int) $fresh->assignee_id) {
                $this->notificationService->notifyTaskAssigned($fresh, $actor);
            }
        }

        return $fresh;
    }

    /**
     * @param  list<int>  $labelIds
     */
    public function syncTaskLabels(Project $project, Task $task, array $labelIds): void
    {
        $this->assertTaskBelongsToProject($task, $project);

        $ids = array_values(array_unique(array_map('intval', $labelIds)));

        if ($ids === []) {
            $task->labels()->detach();

            return;
        }

        $count = Label::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $ids)
            ->count();

        if ($count !== count($ids)) {
            throw new InvalidArgumentException(__('One or more labels are invalid for this project.'));
        }

        $task->labels()->sync($ids);
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

    /**
     * @param  list<array{ column_id: int, task_ids: list<int> }>  $layout
     */
    public function syncBoardTaskLayout(Project $project, array $layout): void
    {
        $this->taskRepository->syncBoardTaskLayout($project, $layout);
    }

    public function markTaskComplete(Project $project, Task $task, ?User $actor = null): Task
    {
        $this->assertTaskBelongsToProject($task, $project);

        if ($task->isCompleted()) {
            return $task;
        }

        $task->load('outgoingDependencies.prerequisite');

        foreach ($task->outgoingDependencies as $edge) {
            $prereq = $edge->prerequisite;

            if (! $prereq->isCompleted()) {
                throw new InvalidArgumentException(
                    __('Complete all prerequisite tasks before marking this task done.'),
                );
            }
        }

        $completed = $this->taskRepository->setTaskCompleted($task);
        $this->activityLogService->recordTaskCompleted($completed, $actor);

        return $completed;
    }

    public function markTaskIncomplete(Project $project, Task $task, ?User $actor = null): Task
    {
        $this->assertTaskBelongsToProject($task, $project);

        $open = $this->taskRepository->setTaskIncomplete($task);
        $this->activityLogService->recordTaskReopened($open, $actor);

        return $open;
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
