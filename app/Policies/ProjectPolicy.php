<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;

class ProjectPolicy
{
    /**
     * View a single project (must be able to access its team).
     */
    public function view(User $user, Project $project): bool
    {
        return $user->can('view', $project->team);
    }

    /**
     * Create a project within a team (owner or team admin).
     */
    public function create(User $user, ?Team $team = null): bool
    {
        if ($team === null) {
            return false;
        }

        return $user->can('manageProjects', $team);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->can('manageProjects', $project->team);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can('manageProjects', $project->team);
    }

    public function archive(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function unarchive(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }
}
