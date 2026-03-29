<?php

use App\Models\ActivityLog;
use App\Models\Column;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;

it('exposes board columns with tasks and delegates moves', function () {
    $project = Project::factory()->create();
    $columnA = Column::factory()->forProject($project)->atPosition(0)->create();
    $columnB = Column::factory()->forProject($project)->atPosition(1)->create();
    $task = Task::factory()->forColumn($columnA)->create(['title' => 'Move me']);
    $actor = User::factory()->create();
    $service = app(TaskService::class);

    $board = $service->boardColumnsWithTasks($project);

    expect($board->pluck('id')->all())->toContain($columnA->id, $columnB->id)
        ->and(
            $board->firstWhere('id', $columnA->id)?->tasks->pluck('title')->all(),
        )->toBe(['Move me']);

    $service->moveTaskToColumn($task, $columnB, $actor);

    expect($task->fresh()->column_id)->toBe($columnB->id);

    $service->deleteTask($task, $actor);

    expect(Task::query()->whereKey($task->id)->exists())->toBeFalse()
        ->and(ActivityLog::query()
            ->where('project_id', $project->id)
            ->where('event', 'task.moved')
            ->where('actor_id', $actor->id)
            ->exists())->toBeTrue()
        ->and(ActivityLog::query()
            ->where('project_id', $project->id)
            ->where('event', 'task.deleted')
            ->where('actor_id', $actor->id)
            ->exists())->toBeTrue();
});
