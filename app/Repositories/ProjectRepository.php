<?php

namespace App\Repositories;

use App\Models\Project;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;

class ProjectRepository
{
    /**
     * @return Collection<int, Project>
     */
    public function listProjectsForTeam(Team $team, bool $includeArchived = false, ?string $nameSearch = null): Collection
    {
        $query = Project::query()
            ->where('team_id', $team->id)
            ->orderByDesc('id');

        if (! $includeArchived) {
            $query->notArchived();
        }

        if ($nameSearch !== null && $nameSearch !== '') {
            $escaped = addcslashes($nameSearch, '%_\\');
            $query->where('name', 'like', '%'.$escaped.'%');
        }

        return $query->get();
    }

    public function createProject(Team $team, string $name): Project
    {
        return Project::query()->create([
            'team_id' => $team->id,
            'name' => $name,
        ]);
    }

    public function updateProject(Project $project, string $name): Project
    {
        $project->update([
            'name' => $name,
        ]);

        return $project->refresh();
    }

    public function archiveProject(Project $project): void
    {
        $project->update([
            'archived_at' => now(),
        ]);
    }

    public function restoreProject(Project $project): void
    {
        $project->update([
            'archived_at' => null,
        ]);
    }

    public function deleteProject(Project $project): void
    {
        $project->delete();
    }
}
