<?php

use App\Models\Column;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\CommentService;

it('manages comments for a task in a known team project', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($member)->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create();
    $service = app(CommentService::class);

    $created = $service->createComment($task, $member, 'Looks good');
    $service->createComment($task, $owner, 'Ship it');

    $list = $service->listTaskComments($task);

    expect($list)->toHaveCount(2)
        ->and($list->first()->body)->toBe('Looks good')
        ->and($list->first()->user->id)->toBe($member->id);

    $updated = $service->updateComment($created, 'Looks really good');
    expect($updated->body)->toBe('Looks really good');

    $service->deleteComment($updated);
    expect($service->listTaskComments($task))->toHaveCount(1);
});
