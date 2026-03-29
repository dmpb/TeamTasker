<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderColumnsRequest;
use App\Http\Requests\StoreColumnRequest;
use App\Http\Requests\UpdateColumnRequest;
use App\Models\Column;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Services\ColumnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ColumnController extends Controller
{
    public function __construct(protected ColumnService $columnService) {}

    public function board(Request $request, Team $team, Project $project): Response
    {
        $this->authorize('view', $project);

        abort_unless($project->team_id === $team->id, 404);

        /** @var User|null $user */
        $user = $request->user();

        $columns = $this->columnService->listProjectColumns($project);

        return Inertia::render('teams/projects/board', [
            'team' => $team->only(['id', 'name', 'owner_id']),
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'archived_at' => $project->archived_at?->toIso8601String(),
            ],
            'columns' => $columns->map(static function (Column $column): array {
                return [
                    'id' => $column->id,
                    'name' => $column->name,
                    'position' => $column->position,
                ];
            })->values()->all(),
            'can' => [
                'manageColumns' => $user?->can('update', $project) ?? false,
            ],
        ]);
    }

    public function store(StoreColumnRequest $request, Team $team, Project $project): RedirectResponse
    {
        abort_unless($project->team_id === $team->id, 404);

        /** @var array{ name: string, position?: int|null } $validated */
        $validated = $request->validated();

        $position = array_key_exists('position', $validated) ? $validated['position'] : null;

        $this->columnService->createColumn($project, $validated['name'], $position);

        return redirect()->route('teams.projects.board', [$team, $project]);
    }

    public function update(UpdateColumnRequest $request, Team $team, Project $project, Column $column): RedirectResponse
    {
        abort_unless($project->team_id === $team->id, 404);

        /** @var array{ name: string } $validated */
        $validated = $request->validated();

        $this->columnService->updateColumn($column, $validated['name']);

        return redirect()->route('teams.projects.board', [$team, $project]);
    }

    public function destroy(Team $team, Project $project, Column $column): RedirectResponse
    {
        abort_unless($project->team_id === $team->id, 404);

        $this->authorize('delete', $column);

        $this->columnService->deleteColumn($column);

        return redirect()->route('teams.projects.board', [$team, $project]);
    }

    public function reorder(ReorderColumnsRequest $request, Team $team, Project $project): RedirectResponse
    {
        abort_unless($project->team_id === $team->id, 404);

        /** @var array{ column_ids: list<int|string> } $validated */
        $validated = $request->validated();

        $this->columnService->reorderColumns($project, $validated['column_ids']);

        return redirect()->route('teams.projects.board', [$team, $project]);
    }
}
