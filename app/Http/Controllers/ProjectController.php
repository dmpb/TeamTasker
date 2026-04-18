<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexTeamProjectsRequest;
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

    private function redirectToTeamProjectsIndex(Team $team, Request $request, array $sessionFlash = []): RedirectResponse
    {
        $url = route('teams.projects.index', [$team], false);
        $qs = $request->getQueryString();
        if ($qs !== null && $qs !== '') {
            $url .= '?'.$qs;
        }

        $redirect = redirect()->to($url);
        foreach ($sessionFlash as $key => $value) {
            $redirect->with($key, $value);
        }

        return $redirect;
    }

    public function index(IndexTeamProjectsRequest $request, Team $team): Response
    {
        $this->authorize('view', $team);

        /** @var User $user */
        $user = $request->user();

        $canManageProjects = $user->can('manageProjects', $team);

        /** @var array{ q?: string|null, per_page?: int, archive_scope?: string } $validated */
        $validated = $request->validated();
        $perPage = max(1, min((int) ($validated['per_page'] ?? 12), 50));
        $nameSearch = $validated['q'] ?? null;

        $requestedScope = (string) ($validated['archive_scope'] ?? 'active');
        $archiveScope = $canManageProjects && in_array($requestedScope, ['active', 'all', 'archived'], true)
            ? $requestedScope
            : 'active';

        $projects = $this->projectService
            ->paginateTeamProjects($team, $perPage, $archiveScope, $nameSearch)
            ->withQueryString();

        $projects->setCollection(
            $projects->getCollection()->map(static function (Project $project): array {
                return [
                    'id' => $project->uuid,
                    'name' => $project->name,
                    'archived_at' => $project->archived_at?->toIso8601String(),
                ];
            }),
        );

        return Inertia::render('teams/projects/index', [
            'team' => [
                'id' => $team->uuid,
                'name' => $team->name,
                'owner_id' => $team->owner_id,
            ],
            'projects' => $projects,
            'can' => [
                'manageProjects' => $canManageProjects,
            ],
            'filters' => [
                'q' => $nameSearch ?? '',
                'archive_scope' => $archiveScope,
            ],
        ]);
    }

    public function store(StoreProjectRequest $request, Team $team): RedirectResponse
    {
        /** @var array{ name: string } $validated */
        $validated = $request->validated();

        $this->projectService->createProject($team, $validated['name']);

        return $this->redirectToTeamProjectsIndex($team, $request, ['success' => __('Project created.')]);
    }

    public function update(UpdateProjectRequest $request, Team $team, Project $project): RedirectResponse
    {
        /** @var array{ name: string } $validated */
        $validated = $request->validated();

        $this->projectService->updateProject($project, $validated['name']);

        return $this->redirectToTeamProjectsIndex($team, $request, ['success' => __('Project updated.')]);
    }

    public function archive(Request $request, Team $team, Project $project): RedirectResponse
    {
        $this->authorize('archive', $project);

        $this->projectService->archiveProject($project);

        return $this->redirectToTeamProjectsIndex($team, $request, [
            'success' => __('Project archived.'),
            'undo' => [
                'method' => 'post',
                'url' => route('teams.projects.unarchive', [$team, $project], false),
                'label' => __('Restore project'),
            ],
        ]);
    }

    public function unarchive(Request $request, Team $team, Project $project): RedirectResponse
    {
        $this->authorize('unarchive', $project);

        $this->projectService->unarchiveProject($project);

        return $this->redirectToTeamProjectsIndex($team, $request, ['success' => __('Project restored.')]);
    }

    public function destroy(Request $request, Team $team, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $this->projectService->deleteProject($project);

        return $this->redirectToTeamProjectsIndex($team, $request, ['success' => __('Project deleted.')]);
    }
}
