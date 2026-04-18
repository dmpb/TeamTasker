<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderTaskChecklistRequest;
use App\Http\Requests\StoreTaskChecklistItemRequest;
use App\Http\Requests\UpdateTaskChecklistItemRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\Team;
use App\Services\TaskChecklistService;
use Illuminate\Http\RedirectResponse;

class TaskChecklistItemController extends Controller
{
    public function __construct(protected TaskChecklistService $checklistService) {}

    public function store(StoreTaskChecklistItemRequest $request, Team $team, Project $project, Task $task): RedirectResponse
    {
        /** @var array{ title: string } $validated */
        $validated = $request->validated();

        $this->checklistService->addItem($task, $validated['title'], $request->user());

        return redirect()
            ->route('teams.projects.tasks.show', [$team, $project, $task])
            ->with('success', __('Checklist item added.'));
    }

    public function update(
        UpdateTaskChecklistItemRequest $request,
        Team $team,
        Project $project,
        Task $task,
        TaskChecklistItem $checklistItem,
    ): RedirectResponse {
        /** @var array{ title: string, is_completed: bool } $validated */
        $validated = $request->validated();

        $this->checklistService->updateItem(
            $task,
            $checklistItem,
            $validated['title'],
            $validated['is_completed'],
        );

        return redirect()
            ->route('teams.projects.tasks.show', [$team, $project, $task])
            ->with('success', __('Checklist item updated.'));
    }

    public function destroy(Team $team, Project $project, Task $task, TaskChecklistItem $checklistItem): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->checklistService->deleteItem($task, $checklistItem);

        return redirect()
            ->route('teams.projects.tasks.show', [$team, $project, $task])
            ->with('success', __('Checklist item removed.'));
    }

    public function reorder(ReorderTaskChecklistRequest $request, Team $team, Project $project, Task $task): RedirectResponse
    {
        /** @var array{ item_ids: list<int|string> } $validated */
        $validated = $request->validated();

        $ids = array_values(array_map(static fn (mixed $id): int => (int) $id, $validated['item_ids']));

        $this->checklistService->reorderItems($task, $ids);

        return redirect()
            ->route('teams.projects.tasks.show', [$team, $project, $task])
            ->with('success', __('Checklist reordered.'));
    }
}
