<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskShowController extends Controller
{
    public function __invoke(Request $request, Team $team, Project $project, Task $task): Response
    {
        $this->authorize('view', $task);

        if ($task->project_id !== $project->id || $project->team_id !== $team->id) {
            abort(404);
        }

        $task->load([
            'column',
            'assignee',
            'labels',
            'checklistItems',
            'outgoingDependencies.prerequisite',
        ]);

        $project->loadMissing('labels');

        $canManageTasks = $request->user()?->can('update', $task) ?? false;

        $assignableUsers = $canManageTasks
            ? $this->assignableUsersForTeam($team)
            : [];

        $projectTasks = Task::query()
            ->where('project_id', $project->id)
            ->whereKeyNot($task->id)
            ->orderBy('title')
            ->limit(200)
            ->get(['id', 'title', 'completed_at']);

        return Inertia::render('teams/projects/tasks/show', [
            'team' => [
                'id' => $team->uuid,
                'name' => $team->name,
                'owner_id' => $team->owner_id,
            ],
            'project' => [
                'id' => $project->uuid,
                'name' => $project->name,
                'archived_at' => $project->archived_at?->toIso8601String(),
            ],
            'labels' => $project->labels->map(static fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'color' => $l->color,
            ])->values()->all(),
            'project_tasks' => $projectTasks->map(static fn (Task $t): array => [
                'id' => $t->id,
                'title' => $t->title,
                'is_completed' => $t->isCompleted(),
            ])->values()->all(),
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'due_date' => $task->due_date?->toDateString(),
                'priority' => $task->priority->value,
                'completed_at' => $task->completed_at?->toIso8601String(),
                'is_completed' => $task->isCompleted(),
                'column' => [
                    'id' => $task->column->id,
                    'name' => $task->column->name,
                ],
                'assignee' => $task->assignee_id === null || $task->assignee === null
                    ? null
                    : [
                        'id' => $task->assignee->id,
                        'name' => $task->assignee->name,
                    ],
                'label_ids' => $task->labels->pluck('id')->values()->all(),
                'checklist_items' => $task->checklistItems->map(static fn ($item): array => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'position' => $item->position,
                    'is_completed' => $item->is_completed,
                ])->values()->all(),
                'dependencies' => $task->outgoingDependencies->map(static function ($dep): array {
                    $p = $dep->prerequisite;

                    return [
                        'prerequisite_task_id' => $p->id,
                        'title' => $p->title,
                        'is_completed' => $p->isCompleted(),
                    ];
                })->values()->all(),
            ],
            'assignableUsers' => $assignableUsers,
            'can' => [
                'manageTasks' => $canManageTasks,
            ],
        ]);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function assignableUsersForTeam(Team $team): array
    {
        $ids = collect([$team->owner_id])
            ->merge($team->members()->pluck('user_id'))
            ->unique()
            ->values();

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
            ])
            ->values()
            ->all();
    }
}
