<?php

namespace App\Repositories;

use App\Enums\TaskPriority;
use App\Models\Column;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
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
    public function listColumnsWithTasksOrderedForProject(
        Project $project,
        ?int $columnIdFilter = null,
        ?int $assigneeIdFilter = null,
        ?string $titleSearch = null,
        ?int $labelIdFilter = null,
        ?string $priorityFilter = null,
        ?string $dueFilter = null,
    ): Collection {
        $columnsQuery = Column::query()
            ->where('project_id', $project->id)
            ->orderBy('position')
            ->orderBy('id');

        if ($columnIdFilter !== null) {
            $columnsQuery->where('id', $columnIdFilter);
        }

        return $columnsQuery
            ->with([
                'tasks' => static function ($query) use ($assigneeIdFilter, $titleSearch, $labelIdFilter, $priorityFilter, $dueFilter): void {
                    $query->with(['assignee', 'labels'])
                        ->withCount([
                            'checklistItems as checklist_items_total',
                            'checklistItems as checklist_items_done' => static function ($q): void {
                                $q->where('is_completed', true);
                            },
                        ])
                        ->orderBy('position')
                        ->orderBy('id');

                    if ($assigneeIdFilter !== null) {
                        $query->where('assignee_id', $assigneeIdFilter);
                    }

                    if ($titleSearch !== null && $titleSearch !== '') {
                        $escaped = addcslashes($titleSearch, '%_\\');
                        $query->where('title', 'like', '%'.$escaped.'%');
                    }

                    if ($labelIdFilter !== null) {
                        $query->whereHas('labels', static function ($labels) use ($labelIdFilter): void {
                            $labels->where('labels.id', $labelIdFilter);
                        });
                    }

                    if ($priorityFilter !== null && $priorityFilter !== '') {
                        $query->where('priority', $priorityFilter);
                    }

                    if ($dueFilter !== null && $dueFilter !== '') {
                        $today = Carbon::today();

                        match ($dueFilter) {
                            'overdue' => $query->whereNotNull('due_date')
                                ->where('due_date', '<', $today)
                                ->whereNull('completed_at'),
                            'today' => $query->whereDate('due_date', $today),
                            'this_week' => $query->whereNotNull('due_date')
                                ->whereBetween('due_date', [
                                    $today->copy()->startOfWeek(),
                                    $today->copy()->endOfWeek(),
                                ]),
                            'no_due' => $query->whereNull('due_date'),
                            default => null,
                        };
                    }
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
        ?Carbon $dueDate = null,
        ?TaskPriority $priority = null,
    ): Task {
        if ($column->project_id !== $project->id) {
            throw new InvalidArgumentException('Column does not belong to the given project.');
        }

        $attempts = 0;

        while (true) {
            try {
                return DB::transaction(function () use ($project, $column, $title, $description, $assignee, $position, $dueDate, $priority): Task {
                    $resolvedPosition = $position;

                    if ($resolvedPosition === null) {
                        Task::query()
                            ->where('column_id', $column->id)
                            ->lockForUpdate()
                            ->get();

                        $resolvedPosition = $this->nextPositionForColumn($column);
                    }

                    return Task::query()->create([
                        'project_id' => $project->id,
                        'column_id' => $column->id,
                        'title' => $title,
                        'description' => $description,
                        'assignee_id' => $assignee?->id,
                        'position' => $resolvedPosition,
                        'due_date' => $dueDate,
                        'priority' => ($priority ?? TaskPriority::Medium)->value,
                    ]);
                });
            } catch (QueryException $e) {
                if (! $this->isUniqueViolation($e) || ++$attempts >= 3 || $position !== null) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function updateTask(
        Task $task,
        string $title,
        ?string $description = null,
        ?User $assignee = null,
        array $extra = [],
    ): Task {
        $data = array_merge([
            'title' => $title,
            'description' => $description,
            'assignee_id' => $assignee?->id,
        ], $extra);

        $task->update($data);

        return $task->refresh();
    }

    public function setTaskCompleted(Task $task, ?Carbon $at = null): Task
    {
        $task->update([
            'completed_at' => $at ?? now(),
        ]);

        return $task->refresh();
    }

    public function setTaskIncomplete(Task $task): Task
    {
        $task->update([
            'completed_at' => null,
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

            $attempts = 0;

            while (true) {
                try {
                    Task::query()
                        ->where('column_id', $targetColumn->id)
                        ->lockForUpdate()
                        ->get();

                    $nextPosition = $this->nextPositionForColumn($targetColumn);

                    $task->update([
                        'column_id' => $targetColumn->id,
                        'position' => $nextPosition,
                    ]);

                    return $task->refresh();
                } catch (QueryException $e) {
                    if (! $this->isUniqueViolation($e) || ++$attempts >= 3) {
                        throw $e;
                    }
                }
            }
        });
    }

    /**
     * Atomically assigns each project task to a column and contiguous positions (0..n-1 per column).
     *
     * @param  list<array{ column_id: int, task_ids: list<int> }>  $layout
     */
    public function syncBoardTaskLayout(Project $project, array $layout): void
    {
        DB::transaction(function () use ($project, $layout): void {
            $columnIds = Column::query()
                ->where('project_id', $project->id)
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->values()
                ->all();

            $expectedTaskIds = Task::query()
                ->where('project_id', $project->id)
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->values()
                ->all();

            if (count($layout) !== count($columnIds)) {
                throw new InvalidArgumentException(__('Every board column must be included in the sync payload.'));
            }

            $seenColumns = [];
            $listedTaskIds = [];

            foreach ($layout as $row) {
                $columnId = (int) $row['column_id'];

                if (! in_array($columnId, $columnIds, true)) {
                    throw new InvalidArgumentException(__('Invalid column for this project.'));
                }

                if (isset($seenColumns[$columnId])) {
                    throw new InvalidArgumentException(__('Duplicate column in board sync payload.'));
                }

                $seenColumns[$columnId] = true;

                foreach ($row['task_ids'] as $taskId) {
                    $taskId = (int) $taskId;

                    if (in_array($taskId, $listedTaskIds, true)) {
                        throw new InvalidArgumentException(__('Duplicate task in board sync payload.'));
                    }

                    $listedTaskIds[] = $taskId;
                }
            }

            $expectedSorted = $expectedTaskIds;
            $listedSorted = $listedTaskIds;
            sort($expectedSorted);
            sort($listedSorted);

            if ($expectedSorted !== $listedSorted) {
                throw new InvalidArgumentException(__('Task list must include every project task exactly once.'));
            }

            Task::query()
                ->where('project_id', $project->id)
                ->lockForUpdate()
                ->get();

            $tempBase = 1_000_000;
            $seq = 0;
            foreach ($expectedTaskIds as $taskId) {
                Task::query()
                    ->whereKey((int) $taskId)
                    ->where('project_id', $project->id)
                    ->update(['position' => $tempBase + $seq]);
                $seq++;
            }

            foreach ($layout as $row) {
                $columnId = (int) $row['column_id'];

                foreach ($row['task_ids'] as $position => $taskId) {
                    Task::query()
                        ->whereKey((int) $taskId)
                        ->where('project_id', $project->id)
                        ->update([
                            'column_id' => $columnId,
                            'position' => $position,
                        ]);
                }
            }
        });
    }

    public function deleteTask(Task $task): void
    {
        $task->delete();
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23505';
    }
}
