<?php

namespace App\Repositories;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ActivityLogRepository
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function createLog(
        Project $project,
        ?User $actor,
        string $event,
        Model $subject,
        array $metadata = [],
    ): ActivityLog {
        return ActivityLog::query()->create([
            'project_id' => $project->id,
            'actor_id' => $actor?->id,
            'event' => $event,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    public function listForProject(
        Project $project,
        int $limit = 50,
        ?string $event = null,
        ?int $actorId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $query = null,
    ): Collection {
        $queryBuilder = ActivityLog::query()
            ->where('project_id', $project->id)
            ->with('actor')
            ->latest();

        if ($event !== null && $event !== '') {
            $queryBuilder->where('event', $event);
        }

        if ($actorId !== null) {
            $queryBuilder->where('actor_id', $actorId);
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $queryBuilder->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        if ($dateTo !== null && $dateTo !== '') {
            $queryBuilder->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        if ($query !== null && $query !== '') {
            $escaped = addcslashes($query, '%_\\');
            $queryBuilder->where('event', 'like', '%'.$escaped.'%');
        }

        return $queryBuilder->limit($limit)->get();
    }
}
