<?php

use App\Models\Column;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskRepository;

it('creates a task when the column belongs to the project', function () {
    $project = Project::factory()->create();
    $column = Column::factory()->forProject($project)->create();
    $repository = app(TaskRepository::class);

    $task = $repository->createTask($project, $column, 'Fix bug', 'Details');

    expect($task->project_id)->toBe($project->id)
        ->and($task->column_id)->toBe($column->id)
        ->and($task->title)->toBe('Fix bug')
        ->and($task->description)->toBe('Details')
        ->and($task->position)->toBe(0);
});

it('throws when creating a task for a column outside the project', function () {
    $projectOne = Project::factory()->create();
    $projectTwo = Project::factory()->create();
    $columnOnTwo = Column::factory()->forProject($projectTwo)->create();
    $repository = app(TaskRepository::class);

    expect(fn () => $repository->createTask($projectOne, $columnOnTwo, 'X'))
        ->toThrow(InvalidArgumentException::class);
});

it('moves a task to another column in the same project', function () {
    $project = Project::factory()->create();
    $columnA = Column::factory()->forProject($project)->atPosition(0)->create();
    $columnB = Column::factory()->forProject($project)->atPosition(1)->create();
    $task = Task::factory()->forColumn($columnA)->create(['title' => 'T']);
    $repository = app(TaskRepository::class);

    $repository->moveTaskToColumn($task, $columnB);

    expect($task->fresh()->column_id)->toBe($columnB->id)
        ->and($task->fresh()->position)->toBe(0)
        ->and($task->fresh()->project_id)->toBe($project->id);
});

it('appends a moved task after existing tasks in the target column', function () {
    $project = Project::factory()->create();
    $columnA = Column::factory()->forProject($project)->atPosition(0)->create();
    $columnB = Column::factory()->forProject($project)->atPosition(1)->create();
    Task::factory()->forColumn($columnB)->atPosition(0)->create();
    $moving = Task::factory()->forColumn($columnA)->atPosition(0)->create();
    $repository = app(TaskRepository::class);

    $repository->moveTaskToColumn($moving, $columnB);

    expect($moving->fresh()->position)->toBe(1);
});

it('throws when moving a task to a column in another project', function () {
    $projectOne = Project::factory()->create();
    $projectTwo = Project::factory()->create();
    $columnA = Column::factory()->forProject($projectOne)->create();
    $columnB = Column::factory()->forProject($projectTwo)->create();
    $task = Task::factory()->forColumn($columnA)->create();
    $repository = app(TaskRepository::class);

    expect(fn () => $repository->moveTaskToColumn($task, $columnB))
        ->toThrow(InvalidArgumentException::class);
});

it('does not change the task when moving to the same column', function () {
    $column = Column::factory()->create();
    $task = Task::factory()->forColumn($column)->atPosition(3)->create();
    $repository = app(TaskRepository::class);

    $repository->moveTaskToColumn($task, $column);

    expect($task->fresh()->column_id)->toBe($column->id)
        ->and($task->fresh()->position)->toBe(3);
});

it('loads columns with tasks ordered for the board without extra queries on tasks', function () {
    $project = Project::factory()->create();
    $columnFirst = Column::factory()->forProject($project)->atPosition(0)->create(['name' => 'A']);
    $columnSecond = Column::factory()->forProject($project)->atPosition(1)->create(['name' => 'B']);
    Task::factory()->forColumn($columnSecond)->atPosition(0)->create(['title' => 'Later']);
    Task::factory()->forColumn($columnFirst)->atPosition(0)->create(['title' => 'First']);
    $repository = app(TaskRepository::class);

    $columns = $repository->listColumnsWithTasksOrderedForProject($project);

    expect($columns->pluck('name')->all())->toBe(['A', 'B'])
        ->and($columns->first()->relationLoaded('tasks'))->toBeTrue()
        ->and($columns->first()->tasks->pluck('title')->all())->toBe(['First'])
        ->and($columns->last()->tasks->pluck('title')->all())->toBe(['Later']);
});

it('updates task title description and assignee', function () {
    $user = User::factory()->create();
    $column = Column::factory()->create();
    $task = Task::factory()->forColumn($column)->create(['title' => 'Old']);
    $repository = app(TaskRepository::class);

    $updated = $repository->updateTask($task, 'New', 'Desc', $user);

    expect($updated->title)->toBe('New')
        ->and($updated->description)->toBe('Desc')
        ->and($updated->assignee_id)->toBe($user->id);
});

it('deletes a task', function () {
    $task = Task::factory()->create();
    $repository = app(TaskRepository::class);

    $repository->deleteTask($task);

    expect(Task::query()->whereKey($task->id)->exists())->toBeFalse();
});

it('cascades task deletion when the project is deleted', function () {
    $task = Task::factory()->create();
    $project = $task->project;
    $project->delete();

    expect(Task::query()->whereKey($task->id)->exists())->toBeFalse();
});
