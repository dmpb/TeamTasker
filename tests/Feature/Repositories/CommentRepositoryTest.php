<?php

use App\Models\Column;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Repositories\CommentRepository;

it('lists comments for a task with authors', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create();
    $user = User::factory()->create();
    TeamMember::factory()->forTeam($team)->forUser($user)->create();
    $repository = app(CommentRepository::class);

    $first = $repository->createComment($task, $user, 'First');
    $second = $repository->createComment($task, $owner, 'Second');

    $list = $repository->listCommentsForTask($task);

    expect($list->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($list->first()->relationLoaded('user'))->toBeTrue()
        ->and($list->first()->user->id)->toBe($user->id);
});

it('creates updates and deletes a comment', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create();
    $repository = app(CommentRepository::class);

    $comment = $repository->createComment($task, $owner, 'Original');

    expect($comment->task_id)->toBe($task->id)
        ->and($comment->user_id)->toBe($owner->id)
        ->and($comment->body)->toBe('Original');

    $updated = $repository->updateComment($comment, 'Edited');
    expect($updated->body)->toBe('Edited');

    $repository->deleteComment($updated);
    expect(Comment::query()->whereKey($updated->id)->exists())->toBeFalse();
});

it('cascades comment deletion when the task is deleted', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create();
    $repository = app(CommentRepository::class);
    $comment = $repository->createComment($task, $owner, 'Hi');

    $task->delete();

    expect(Comment::query()->whereKey($comment->id)->exists())->toBeFalse();
});
