<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\DashboardRepository;

class DashboardService
{
    public function __construct(public DashboardRepository $dashboardRepository) {}

    /**
     * @return array{
     *     stats: array{my_assigned_tasks: int, active_projects: int},
     *     myTasks: list<array<string, mixed>>,
     *     recentTasks: list<array<string, mixed>>,
     *     activeProjects: list<array<string, mixed>>,
     *     recentActivity: list<array<string, mixed>>
     * }
     */
    public function buildDashboardPayload(User $user): array
    {
        $myTasks = $this->dashboardRepository->listMyAssignedTasks($user, 15);
        $recentTasks = $this->dashboardRepository->listRecentTasksForUser($user, 10);
        $projects = $this->dashboardRepository->listActiveProjectsForUser($user, 12);
        $activity = $this->dashboardRepository->listRecentActivityForUser($user, 15);

        return [
            'stats' => [
                'my_assigned_tasks' => $this->dashboardRepository->countMyAssignedTasks($user),
                'active_projects' => $this->dashboardRepository->countActiveProjects($user),
            ],
            'myTasks' => $myTasks->map(fn (Task $task): array => $this->serializeTaskForDashboard($task))->values()->all(),
            'recentTasks' => $recentTasks->map(fn (Task $task): array => $this->serializeTaskForDashboard($task))->values()->all(),
            'activeProjects' => $projects->map(fn (Project $project): array => $this->serializeProjectForDashboard($project))->values()->all(),
            'recentActivity' => $activity->map(fn (ActivityLog $log): array => $this->serializeActivityForDashboard($log))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTaskForDashboard(Task $task): array
    {
        $project = $task->project;
        $team = $project->team;

        return [
            'id' => $task->id,
            'title' => $task->title,
            'updated_at' => $task->updated_at?->toIso8601String(),
            'due_date' => $task->due_date?->toDateString(),
            'priority' => $task->priority->value,
            'is_completed' => $task->isCompleted(),
            'project' => [
                'id' => $project->uuid,
                'name' => $project->name,
            ],
            'team' => [
                'id' => $team->uuid,
                'name' => $team->name,
            ],
            'column' => [
                'id' => $task->column->id,
                'name' => $task->column->name,
            ],
            'assignee' => $task->assignee_id === null || ! $task->relationLoaded('assignee') || $task->assignee === null
                ? null
                : [
                    'id' => $task->assignee->id,
                    'name' => $task->assignee->name,
                ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProjectForDashboard(Project $project): array
    {
        $team = $project->team;

        return [
            'id' => $project->uuid,
            'name' => $project->name,
            'team' => [
                'id' => $team->uuid,
                'name' => $team->name,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeActivityForDashboard(ActivityLog $log): array
    {
        $project = $log->project;
        $team = $project->team;

        return [
            'id' => $log->id,
            'event' => $log->event,
            'created_at' => $log->created_at?->toIso8601String(),
            'actor' => $log->actor ? [
                'id' => $log->actor->id,
                'name' => $log->actor->name,
            ] : null,
            'project' => [
                'id' => $project->uuid,
                'name' => $project->name,
            ],
            'team' => [
                'id' => $team->uuid,
                'name' => $team->name,
            ],
        ];
    }
}
