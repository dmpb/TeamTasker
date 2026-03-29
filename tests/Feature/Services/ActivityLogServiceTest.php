<?php

use App\Models\Column;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\ActivityLogService;

it('records and lists project activity logs for key events', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->create();
    $columnA = Column::factory()->forProject($project)->atPosition(0)->create();
    $columnB = Column::factory()->forProject($project)->atPosition(1)->create();
    $task = Task::factory()->forColumn($columnA)->create();
    $service = app(ActivityLogService::class);

    $service->recordTaskCreated($task, $owner);
    $service->recordTaskMoved($task, $columnA->id, $columnB->id, $owner);
    $service->recordTaskUpdated($task, $owner);
    $service->recordTaskDeleted($task, $owner);

    $logs = $service->listProjectLogs($project, 10);

    expect($logs->pluck('event')->all())
        ->toBe(['task.deleted', 'task.updated', 'task.moved', 'task.created'])
        ->and($logs->first()->project_id)->toBe($project->id)
        ->and($logs->first()->actor_id)->toBe($owner->id)
        ->and($logs->firstWhere('event', 'task.moved')?->metadata)
        ->toMatchArray([
            'from_column_id' => $columnA->id,
            'to_column_id' => $columnB->id,
        ]);
});
