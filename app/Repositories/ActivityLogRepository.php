<?php

namespace App\Repositories;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
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
    public function listForProject(Project $project, int $limit = 50): Collection
    {
        return ActivityLog::query()
            ->where('project_id', $project->id)
            ->with('actor')
            ->latest()
            ->limit($limit)
            ->get();
    }
}
