<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivityLogFilterRequest;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Services\ActivityLogService;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function __construct(protected ActivityLogService $activityLogService) {}

    public function index(ActivityLogFilterRequest $request, Team $team, Project $project): Response
    {
        $this->authorize('viewAny', [ActivityLog::class, $project]);

        /** @var array{ event?: string|null, actor_id?: int|null, date_from?: string|null, date_to?: string|null, q?: string|null } $filters */
        $filters = $request->validated();

        $logs = $this->activityLogService->listProjectLogs(
            $project,
            100,
            $filters['event'] ?? null,
            $filters['actor_id'] ?? null,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            $filters['q'] ?? null,
        );

        $actorIds = ActivityLog::query()
            ->where('project_id', $project->id)
            ->whereNotNull('actor_id')
            ->distinct()
            ->pluck('actor_id');

        $actors = $actorIds->isEmpty()
            ? []
            : User::query()
                ->whereIn('id', $actorIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(static fn (User $u): array => [
                    'id' => $u->id,
                    'name' => $u->name,
                ])
                ->values()
                ->all();

        return Inertia::render('teams/projects/activity/index', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'activityLogs' => $logs->map(static fn (ActivityLog $log): array => [
                'id' => $log->id,
                'event' => $log->event,
                'created_at' => $log->created_at?->toIso8601String(),
                'actor' => $log->actor ? [
                    'id' => $log->actor->id,
                    'name' => $log->actor->name,
                ] : null,
                'subject' => [
                    'type' => class_basename((string) $log->subject_type),
                    'id' => $log->subject_id,
                ],
                'metadata' => $log->metadata,
            ])->values()->all(),
            'actors' => $actors,
            'filters' => [
                'event' => $filters['event'] ?? '',
                'actor_id' => $filters['actor_id'] ?? null,
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'q' => $filters['q'] ?? '',
            ],
        ]);
    }
}
