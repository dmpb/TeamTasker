<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TaskCompletionController extends Controller
{
    public function __construct(protected TaskService $taskService) {}

    public function store(Request $request, Team $team, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        try {
            $this->taskService->markTaskComplete($project, $task, $request->user());
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('teams.projects.tasks.show', [$team, $project, $task])
                ->withErrors(['complete' => $e->getMessage()]);
        }

        return redirect()
            ->route('teams.projects.tasks.show', [$team, $project, $task])
            ->with('success', __('Task marked complete.'));
    }

    public function destroy(Request $request, Team $team, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->taskService->markTaskIncomplete($project, $task, $request->user());

        return redirect()
            ->route('teams.projects.tasks.show', [$team, $project, $task])
            ->with('success', __('Task marked as not done.'));
    }
}
