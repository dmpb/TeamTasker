<?php

namespace App\Http\Controllers;

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
    public function __construct(protected TaskService $taskService) {}

    public function store(StoreTaskRequest $request, Team $team, Project $project, Column $column): RedirectResponse
    {
        abort_unless($project->team_id === $team->id, 404);

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

        return redirect()->route('teams.projects.board', [$team, $project]);
    }

    public function update(UpdateTaskRequest $request, Team $team, Project $project, Task $task): RedirectResponse
    {
        abort_unless($project->team_id === $team->id, 404);

        /** @var array{ title: string, description?: string|null, assignee_id?: int|null } $validated */
        $validated = $request->validated();

        $assignee = ($validated['assignee_id'] ?? null) !== null
            ? User::query()->find($validated['assignee_id'])
            : null;

        $this->taskService->updateTask(
            $task,
            $validated['title'],
            $validated['description'] ?? null,
            $assignee,
            $request->user(),
        );

        return redirect()->route('teams.projects.board', [$team, $project]);
    }

    public function move(MoveTaskRequest $request, Team $team, Project $project, Task $task): RedirectResponse
    {
        abort_unless($project->team_id === $team->id, 404);

        /** @var array{ target_column_id: int } $validated */
        $validated = $request->validated();

        $targetColumn = Column::query()->findOrFail($validated['target_column_id']);

        $this->taskService->moveTaskToColumn($task, $targetColumn, $request->user());

        return redirect()->route('teams.projects.board', [$team, $project]);
    }

    public function destroy(Request $request, Team $team, Project $project, Task $task): RedirectResponse
    {
        abort_unless($project->team_id === $team->id, 404);

        $this->authorize('delete', $task);

        $this->taskService->deleteTask($task, $request->user());

        return redirect()->route('teams.projects.board', [$team, $project]);
    }
}
