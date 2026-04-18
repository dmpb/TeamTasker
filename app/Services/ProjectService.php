<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Team;
use App\Repositories\ProjectRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectService
{
    public function __construct(public ProjectRepository $projectRepository) {}

    public function paginateTeamProjects(
        Team $team,
        int $perPage = 12,
        string $archiveScope = 'active',
        ?string $nameSearch = null,
    ): LengthAwarePaginator {
        return $this->projectRepository->paginateProjectsForTeam($team, $perPage, $archiveScope, $nameSearch);
    }

    public function createProject(Team $team, string $name): Project
    {
        return $this->projectRepository->createProject($team, $name);
    }

    public function updateProject(Project $project, string $name): Project
    {
        return $this->projectRepository->updateProject($project, $name);
    }

    public function archiveProject(Project $project): void
    {
        $this->projectRepository->archiveProject($project);
    }

    public function unarchiveProject(Project $project): void
    {
        $this->projectRepository->restoreProject($project);
    }

    public function deleteProject(Project $project): void
    {
        $this->projectRepository->deleteProject($project);
    }
}
