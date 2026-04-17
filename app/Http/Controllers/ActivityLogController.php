<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Team;
use App\Services\ActivityLogService;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function __construct(protected ActivityLogService $activityLogService) {}

    public function index(Team $team, Project $project): Response
    {
        $this->authorize('viewAny', [ActivityLog::class, $project]);

        $logs = $this->activityLogService->listProjectLogs($project, 100);

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
        ]);
    }
}
