<?php

namespace App\Repositories;

use App\Models\Column;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaskRepository
{
    public function nextPositionForColumn(Column $column): int
    {
        $max = Task::query()->where('column_id', $column->id)->max('position');

        return $max === null ? 0 : (int) $max + 1;
    }

    /**
     * Board payload: one query for columns plus one for all tasks (eager load), ordered for UI.
     *
     * @return Collection<int, Column>
     */
    public function listColumnsWithTasksOrderedForProject(Project $project): Collection
    {
        return Column::query()
            ->where('project_id', $project->id)
            ->orderBy('position')
            ->orderBy('id')
            ->with([
                'tasks' => static function ($query): void {
                    $query->with('assignee')->orderBy('position')->orderBy('id');
                },
            ])
            ->get();
    }

    /**
     * @return Collection<int, Task>
     */
    public function listTasksForColumnOrdered(Column $column): Collection
    {
        return Task::query()
            ->where('column_id', $column->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function createTask(
        Project $project,
        Column $column,
        string $title,
        ?string $description = null,
        ?User $assignee = null,
        ?int $position = null,
    ): Task {
        if ($column->project_id !== $project->id) {
            throw new InvalidArgumentException('Column does not belong to the given project.');
        }

        if ($position === null) {
            $position = $this->nextPositionForColumn($column);
        }

        return Task::query()->create([
            'project_id' => $project->id,
            'column_id' => $column->id,
            'title' => $title,
            'description' => $description,
            'assignee_id' => $assignee?->id,
            'position' => $position,
        ]);
    }

    public function updateTask(
        Task $task,
        string $title,
        ?string $description = null,
        ?User $assignee = null,
    ): Task {
        $task->update([
            'title' => $title,
            'description' => $description,
            'assignee_id' => $assignee?->id,
        ]);

        return $task->refresh();
    }

    /**
     * Moves the task to another column in the same project and appends it at the end of the target column.
     */
    public function moveTaskToColumn(Task $task, Column $targetColumn): Task
    {
        if ($targetColumn->project_id !== $task->project_id) {
            throw new InvalidArgumentException('Target column belongs to another project.');
        }

        return DB::transaction(function () use ($task, $targetColumn): Task {
            if ($task->column_id === $targetColumn->id) {
                return $task;
            }

            $nextPosition = $this->nextPositionForColumn($targetColumn);

            $task->update([
                'column_id' => $targetColumn->id,
                'position' => $nextPosition,
            ]);

            return $task->refresh();
        });
    }

    public function deleteTask(Task $task): void
    {
        $task->delete();
    }
}
