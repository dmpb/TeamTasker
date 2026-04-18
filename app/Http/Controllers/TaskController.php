<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Http\Controllers\Concerns\BuildsBoardRedirectUrl;
use App\Http\Requests\MoveTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\SyncBoardTaskLayoutRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Column;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class TaskController extends Controller
{
    use BuildsBoardRedirectUrl;

    public function __construct(protected TaskService $taskService) {}

    public function store(StoreTaskRequest $request, Team $team, Project $project, Column $column): RedirectResponse
    {
        /** @var array{
         *     title: string,
         *     description?: string|null,
         *     assignee_id?: int|null,
         *     due_date?: string|null,
         *     priority?: string|null,
         *     label_ids?: list<int>|null
         * } $validated */
        $validated = $request->validated();

        $assignee = isset($validated['assignee_id']) && $validated['assignee_id'] !== null
            ? User::query()->find($validated['assignee_id'])
            : null;

        $dueDate = isset($validated['due_date']) && $validated['due_date'] !== null
            ? Carbon::parse((string) $validated['due_date'])->startOfDay()
            : null;

        $priority = isset($validated['priority']) && $validated['priority'] !== null
            ? TaskPriority::from((string) $validated['priority'])
            : null;

        $labelIds = $validated['label_ids'] ?? null;

        $this->taskService->createTask(
            $project,
            $column,
            $validated['title'],
            $validated['description'] ?? null,
            $assignee,
            $request->user(),
            null,
            $dueDate,
            $priority,
            $labelIds,
        );

        return redirect()->to($this->boardUrlWithFilters($team, $project, $request))
            ->with('success', __('Task created.'));
    }

    public function update(UpdateTaskRequest $request, Team $team, Project $project, Task $task): RedirectResponse
    {
        /** @var array{
         *     title: string,
         *     description?: string|null,
         *     assignee_id?: int|null,
         *     due_date?: string|null,
         *     clear_due_date?: bool|null,
         *     priority?: string|null,
         *     label_ids?: list<int>|null
         * } $validated */
        $validated = $request->validated();

        $assignee = ($validated['assignee_id'] ?? null) !== null
            ? User::query()->find($validated['assignee_id'])
            : null;

        $clearDueDate = (bool) ($validated['clear_due_date'] ?? false);

        $dueDate = null;

        if (! $clearDueDate && isset($validated['due_date']) && $validated['due_date'] !== null) {
            $dueDate = Carbon::parse((string) $validated['due_date'])->startOfDay();
        }

        $priority = isset($validated['priority']) && $validated['priority'] !== null
            ? TaskPriority::from((string) $validated['priority'])
            : null;

        $labelIds = null;

        if ($request->boolean('sync_label_ids')) {
            $labelIds = array_values($validated['label_ids'] ?? []);
        }

        $this->taskService->updateTask(
            $project,
            $task,
            $validated['title'],
            $validated['description'] ?? null,
            $assignee,
            $request->user(),
            $dueDate,
            $clearDueDate,
            $priority,
            $labelIds,
        );

        if ($request->boolean('return_to_task')) {
            return redirect()
                ->route('teams.projects.tasks.show', [$team, $project, $task])
                ->with('success', __('Task updated.'));
        }

        return redirect()->to($this->boardUrlWithFilters($team, $project, $request))
            ->with('success', __('Task updated.'));
    }

    public function syncBoard(SyncBoardTaskLayoutRequest $request, Team $team, Project $project): RedirectResponse
    {
        try {
            $this->taskService->syncBoardTaskLayout($project, $request->layoutPayload());
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->to($this->boardUrlWithFilters($team, $project, $request))
                ->withErrors(['board' => $e->getMessage()]);
        }

        return redirect()
            ->to($this->boardUrlWithFilters($team, $project, $request))
            ->with('success', __('Board updated.'));
    }

    public function move(MoveTaskRequest $request, Team $team, Project $project, Task $task): RedirectResponse
    {
        /** @var array{ target_column_id: int } $validated */
        $validated = $request->validated();

        $targetColumn = Column::query()->findOrFail($validated['target_column_id']);

        $this->taskService->moveTaskToColumn($project, $task, $targetColumn, $request->user());

        return redirect()->to($this->boardUrlWithFilters($team, $project, $request))
            ->with('success', __('Task moved.'));
    }

    public function destroy(Request $request, Team $team, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $this->taskService->deleteTask($project, $task, $request->user());

        return redirect()->to($this->boardUrlWithFilters($team, $project, $request))
            ->with('success', __('Task deleted.'));
    }
}
