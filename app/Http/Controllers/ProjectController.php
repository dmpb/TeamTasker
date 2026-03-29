<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(protected ProjectService $projectService) {}

    public function index(Request $request, Team $team): Response
    {
        $this->authorize('view', $team);

        /** @var User $user */
        $user = $request->user();

        $includeArchived = $request->boolean('include_archived')
            && $user->can('manageProjects', $team);

        $projects = $this->projectService->listTeamProjects($team, $includeArchived);

        return Inertia::render('teams/projects/index', [
            'team' => $team->only(['id', 'name', 'owner_id']),
            'projects' => $projects->map(static function (Project $project): array {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'archived_at' => $project->archived_at?->toIso8601String(),
                ];
            })->values()->all(),
            'can' => [
                'manageProjects' => $user->can('manageProjects', $team),
                'showArchived' => $includeArchived,
            ],
        ]);
    }

    public function store(StoreProjectRequest $request, Team $team): RedirectResponse
    {
        /** @var array{ name: string } $validated */
        $validated = $request->validated();

        $this->projectService->createProject($team, $validated['name']);

        return redirect()->route('teams.projects.index', $team);
    }

    public function update(UpdateProjectRequest $request, Team $team, Project $project): RedirectResponse
    {
        /** @var array{ name: string } $validated */
        $validated = $request->validated();

        $this->projectService->updateProject($project, $validated['name']);

        return redirect()->route('teams.projects.index', $team);
    }

    public function archive(Team $team, Project $project): RedirectResponse
    {
        $this->authorize('archive', $project);

        abort_unless($project->team_id === $team->id, 404);

        $this->projectService->archiveProject($project);

        return redirect()->route('teams.projects.index', $team);
    }

    public function unarchive(Team $team, Project $project): RedirectResponse
    {
        $this->authorize('unarchive', $project);

        abort_unless($project->team_id === $team->id, 404);

        $this->projectService->unarchiveProject($project);

        return redirect()->route('teams.projects.index', $team);
    }

    public function destroy(Team $team, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        abort_unless($project->team_id === $team->id, 404);

        $this->projectService->deleteProject($project);

        return redirect()->route('teams.projects.index', $team);
    }
}
