<?php

namespace App\Repositories;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class DashboardRepository
{
    /**
     * Tasks assigned to the user within teams they can access.
     *
     * @return Collection<int, Task>
     */
    public function listMyAssignedTasks(User $user, int $limit = 15): Collection
    {
        return Task::query()
            ->where('assignee_id', $user->id)
            ->whereHas('project.team', static function ($query) use ($user): void {
                $query->accessibleByUser($user);
            })
            ->with(['project.team', 'column'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Recently updated tasks across accessible projects (any assignee).
     *
     * @return Collection<int, Task>
     */
    public function listRecentTasksForUser(User $user, int $limit = 10): Collection
    {
        return Task::query()
            ->whereHas('project.team', static function ($query) use ($user): void {
                $query->accessibleByUser($user);
            })
            ->with(['project.team', 'column', 'assignee'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Non-archived projects in teams the user can access.
     *
     * @return Collection<int, Project>
     */
    public function listActiveProjectsForUser(User $user, int $limit = 12): Collection
    {
        return Project::query()
            ->notArchived()
            ->whereHas('team', static function ($query) use ($user): void {
                $query->accessibleByUser($user);
            })
            ->with('team')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Activity log entries across all projects the user can access.
     *
     * @return Collection<int, ActivityLog>
     */
    public function listRecentActivityForUser(User $user, int $limit = 15): Collection
    {
        $projectIds = Project::query()
            ->whereHas('team', static function ($query) use ($user): void {
                $query->accessibleByUser($user);
            })
            ->pluck('id');

        if ($projectIds->isEmpty()) {
            return new Collection([]);
        }

        return ActivityLog::query()
            ->whereIn('project_id', $projectIds)
            ->with(['actor', 'project.team'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function countMyAssignedTasks(User $user): int
    {
        return Task::query()
            ->where('assignee_id', $user->id)
            ->whereHas('project.team', static function ($query) use ($user): void {
                $query->accessibleByUser($user);
            })
            ->count();
    }

    public function countActiveProjects(User $user): int
    {
        return Project::query()
            ->notArchived()
            ->whereHas('team', static function ($query) use ($user): void {
                $query->accessibleByUser($user);
            })
            ->count();
    }
}
