<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskDependencyRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Services\TaskDependencyService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class TaskDependencyController extends Controller
{
    public function __construct(protected TaskDependencyService $dependencyService) {}

    public function store(StoreTaskDependencyRequest $request, Team $team, Project $project, Task $task): RedirectResponse
    {
        /** @var array{ prerequisite_task_id: int } $validated */
        $validated = $request->validated();

        $prerequisite = Task::query()
            ->where('project_id', $project->id)
            ->findOrFail($validated['prerequisite_task_id']);

        try {
            $this->dependencyService->addDependency($task, $prerequisite);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('teams.projects.tasks.show', [$team, $project, $task])
                ->withErrors(['dependency' => $e->getMessage()]);
        }

        return redirect()
            ->route('teams.projects.tasks.show', [$team, $project, $task])
            ->with('success', __('Dependency added.'));
    }

    public function destroy(Team $team, Project $project, Task $task, int $prerequisiteTask): RedirectResponse
    {
        $this->authorize('update', $task);

        $prerequisite = Task::query()
            ->where('project_id', $project->id)
            ->findOrFail($prerequisiteTask);

        $this->dependencyService->removeDependency($task, $prerequisite);

        return redirect()
            ->route('teams.projects.tasks.show', [$team, $project, $task])
            ->with('success', __('Dependency removed.'));
    }
}
