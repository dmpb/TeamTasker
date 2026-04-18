<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivityLogFilterRequest;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    public function __construct(protected ActivityLogService $activityLogService) {}

    public function index(ActivityLogFilterRequest $request, Team $team, Project $project): InertiaResponse
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
                'id' => $team->uuid,
                'name' => $team->name,
            ],
            'project' => [
                'id' => $project->uuid,
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
            'can' => [
                'exportActivityLog' => $request->user()?->can('exportActivityLog', $project) ?? false,
            ],
        ]);
    }

    public function exportCsv(ActivityLogFilterRequest $request, Team $team, Project $project): StreamedResponse
    {
        $this->authorize('exportActivityLog', $project);

        /** @var array{ event?: string|null, actor_id?: int|null, date_from?: string|null, date_to?: string|null, q?: string|null } $filters */
        $filters = $request->validated();

        $filename = 'project-'.$project->id.'-activity.csv';

        return ResponseFactory::streamDownload(function () use ($project, $filters): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['id', 'event', 'created_at', 'actor_id', 'actor_name', 'subject_type', 'subject_id', 'metadata']);

            $this->activityLogService->eachProjectLogChunk(
                $project,
                500,
                static function ($chunk) use ($handle): void {
                    foreach ($chunk as $log) {
                        /** @var ActivityLog $log */
                        fputcsv($handle, [
                            $log->id,
                            $log->event,
                            $log->created_at?->toIso8601String() ?? '',
                            $log->actor_id ?? '',
                            $log->actor?->name ?? '',
                            class_basename((string) $log->subject_type),
                            $log->subject_id ?? '',
                            $log->metadata === null ? '' : json_encode($log->metadata, JSON_THROW_ON_ERROR),
                        ]);
                    }
                },
                $filters['event'] ?? null,
                $filters['actor_id'] ?? null,
                $filters['date_from'] ?? null,
                $filters['date_to'] ?? null,
                $filters['q'] ?? null,
            );

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
