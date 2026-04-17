<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsBoardRedirectUrl;
use App\Http\Requests\MoveTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Column;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    use BuildsBoardRedirectUrl;

    public function __construct(protected TaskService $taskService) {}

    public function store(StoreTaskRequest $request, Team $team, Project $project, Column $column): RedirectResponse
    {
        /** @var array{ title: string, description?: string|null, assignee_id?: int|null } $validated */
        $validated = $request->validated();

        $assignee = isset($validated['assignee_id']) && $validated['assignee_id'] !== null
            ? User::query()->find($validated['assignee_id'])
            : null;

        $this->taskService->createTask(
            $project,
            $column,
            $validated['title'],
            $validated['description'] ?? null,
            $assignee,
            $request->user(),
        );

        return redirect()->to($this->boardUrlWithFilters($team, $project, $request))
            ->with('success', __('Task created.'));
    }

    public function update(UpdateTaskRequest $request, Team $team, Project $project, Task $task): RedirectResponse
    {
        /** @var array{ title: string, description?: string|null, assignee_id?: int|null } $validated */
        $validated = $request->validated();

        $assignee = ($validated['assignee_id'] ?? null) !== null
            ? User::query()->find($validated['assignee_id'])
            : null;

        $this->taskService->updateTask(
            $project,
            $task,
            $validated['title'],
            $validated['description'] ?? null,
            $assignee,
            $request->user(),
        );

        return redirect()->to($this->boardUrlWithFilters($team, $project, $request))
            ->with('success', __('Task updated.'));
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
