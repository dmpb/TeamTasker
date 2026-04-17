<?php

use App\Models\Column;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\TeamService;
use Tests\TestCase;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('redirects guests from comment routes', function () {
    $team = Team::factory()->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create();
    $comment = Comment::factory()->forTask($task)->create();

    /** @var TestCase $this */
    $this->get(route('teams.projects.tasks.comments.index', [$team, $project, $task]))
        ->assertRedirect(route('login'));

    $this->post(route('teams.projects.tasks.comments.store', [$team, $project, $task]), [
        'body' => 'Hi',
    ])->assertRedirect(route('login'));

    $this->patch(route('teams.projects.tasks.comments.update', [$team, $project, $task, $comment]), [
        'body' => 'Bye',
    ])->assertRedirect(route('login'));

    $this->delete(route('teams.projects.tasks.comments.destroy', [$team, $project, $task, $comment]))
        ->assertRedirect(route('login'));
});

it('forbids users outside the team from listing or creating comments', function () {
    $ownerA = User::factory()->create();
    $teamA = Team::factory()->forOwner($ownerA)->create();
    $projectA = Project::factory()->forTeam($teamA)->create();
    $columnA = Column::factory()->forProject($projectA)->create();
    $taskA = Task::factory()->forColumn($columnA)->create();

    $outsider = User::factory()->create();
    $teamB = Team::factory()->forOwner($outsider)->create();
    TeamMember::factory()->forTeam($teamB)->forUser($outsider)->create();

    /** @var TestCase $this */
    $this->actingAs($outsider)
        ->get(route('teams.projects.tasks.comments.index', [$teamA, $projectA, $taskA]))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->post(route('teams.projects.tasks.comments.store', [$teamA, $projectA, $taskA]), [
            'body' => 'Hello',
        ])
        ->assertForbidden();
});

it('lets team members list and create comments', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($member)->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create();

    /** @var TestCase $this */
    $this->actingAs($member)
        ->get(route('teams.projects.tasks.comments.index', [$team, $project, $task]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('teams/projects/tasks/comments')
            ->where('can.createComments', true)
            ->has('comments', 0));

    $this->actingAs($member)
        ->from(route('teams.projects.tasks.comments.index', [$team, $project, $task]))
        ->post(route('teams.projects.tasks.comments.store', [$team, $project, $task]), [
            'body' => 'First note',
        ])
        ->assertRedirect(route('teams.projects.tasks.comments.index', [$team, $project, $task]));

    expect(Comment::query()->where('task_id', $task->id)->count())->toBe(1);

    $this->actingAs($member)
        ->get(route('teams.projects.tasks.comments.index', [$team, $project, $task]))
        ->assertInertia(fn ($page) => $page
            ->has('comments', 1)
            ->where('comments.0.user.id', $member->id)
            ->where('comments.0.can.update', true)
            ->where('comments.0.can.delete', true));
});

it('blocks comment mutations when the project is archived', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->archived()->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create();
    $comment = Comment::factory()->forTask($task)->byUser($owner)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->post(route('teams.projects.tasks.comments.store', [$team, $project, $task]), [
            'body' => 'Blocked',
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->patch(route('teams.projects.tasks.comments.update', [$team, $project, $task, $comment]), [
            'body' => 'Blocked update',
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->delete(route('teams.projects.tasks.comments.destroy', [$team, $project, $task, $comment]))
        ->assertForbidden();
});

it('returns 404 when the task belongs to another project in the url', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $projectA = Project::factory()->forTeam($team)->create();
    $projectB = Project::factory()->forTeam($team)->create();
    $columnB = Column::factory()->forProject($projectB)->create();
    $taskOnB = Task::factory()->forColumn($columnB)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->get(route('teams.projects.tasks.comments.index', [$team, $projectA, $taskOnB]))
        ->assertNotFound();
});

it('validates comment body on store', function () {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createTeam($owner, ['name' => 'T']);
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->from(route('teams.projects.tasks.comments.index', [$team, $project, $task]))
        ->post(route('teams.projects.tasks.comments.store', [$team, $project, $task]), [
            'body' => '',
        ])
        ->assertRedirect(route('teams.projects.tasks.comments.index', [$team, $project, $task]))
        ->assertSessionHasErrors('body');
});

it('lets the author update their comment', function () {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createTeam($owner, ['name' => 'T']);
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create();
    $comment = Comment::factory()->forTask($task)->byUser($owner)->create(['body' => 'Old']);

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->patch(route('teams.projects.tasks.comments.update', [$team, $project, $task, $comment]), [
            'body' => 'New text',
        ])
        ->assertRedirect(route('teams.projects.tasks.comments.index', [$team, $project, $task]));

    expect($comment->fresh()->body)->toBe('New text');
});

it('forbids plain members from updating another users comment', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($member)->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create();
    $comment = Comment::factory()->forTask($task)->byUser($owner)->create(['body' => 'Owners']);

    /** @var TestCase $this */
    $this->actingAs($member)
        ->patch(route('teams.projects.tasks.comments.update', [$team, $project, $task, $comment]), [
            'body' => 'Hijack',
        ])
        ->assertForbidden();
});

it('returns 404 when the comment does not belong to the task in the url', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->create();
    $columnA = Column::factory()->forProject($project)->atPosition(0)->create();
    $columnB = Column::factory()->forProject($project)->atPosition(1)->create();
    $taskA = Task::factory()->forColumn($columnA)->create();
    $taskB = Task::factory()->forColumn($columnB)->create();
    $commentOnB = Comment::factory()->forTask($taskB)->byUser($owner)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->patch(route('teams.projects.tasks.comments.update', [$team, $project, $taskA, $commentOnB]), [
            'body' => 'X',
        ])
        ->assertNotFound();
});
