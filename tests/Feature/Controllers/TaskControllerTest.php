<?php

use App\Models\Column;
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

it('redirects guests from task routes', function () {
    $team = Team::factory()->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create();

    /** @var TestCase $this */
    $this->post(route('teams.projects.columns.tasks.store', [$team, $project, $column]), [
        'title' => 'T',
    ])->assertRedirect(route('login'));

    $this->patch(route('teams.projects.tasks.update', [$team, $project, $task]), [
        'title' => 'U',
    ])->assertRedirect(route('login'));

    $this->post(route('teams.projects.tasks.move', [$team, $project, $task]), [
        'target_column_id' => $column->id,
    ])->assertRedirect(route('login'));

    $this->delete(route('teams.projects.tasks.destroy', [$team, $project, $task]))->assertRedirect(route('login'));
});

it('forbids task mutations for plain team members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($member)->create();
    $project = Project::factory()->forTeam($team)->create();
    $columnA = Column::factory()->forProject($project)->atPosition(0)->create();
    $columnB = Column::factory()->forProject($project)->atPosition(1)->create();
    $task = Task::factory()->forColumn($columnA)->create();

    /** @var TestCase $this */
    $this->actingAs($member)
        ->post(route('teams.projects.columns.tasks.store', [$team, $project, $columnA]), [
            'title' => 'Nope',
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->patch(route('teams.projects.tasks.update', [$team, $project, $task]), [
            'title' => 'Hijack',
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('teams.projects.tasks.move', [$team, $project, $task]), [
            'target_column_id' => $columnB->id,
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('teams.projects.tasks.destroy', [$team, $project, $task]))
        ->assertForbidden();
});

it('lets owners create update move and delete tasks', function () {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createTeam($owner, ['name' => 'Ship']);
    $project = Project::factory()->forTeam($team)->create();
    $columnA = Column::factory()->forProject($project)->atPosition(0)->create();
    $columnB = Column::factory()->forProject($project)->atPosition(1)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->from(route('teams.projects.board', [$team, $project]))
        ->post(route('teams.projects.columns.tasks.store', [$team, $project, $columnA]), [
            'title' => 'Ship it',
            'description' => 'Details',
        ])
        ->assertRedirect(route('teams.projects.board', [$team, $project]));

    $task = Task::query()->where('column_id', $columnA->id)->first();
    expect($task)->not->toBeNull()
        ->and($task->title)->toBe('Ship it')
        ->and($task->description)->toBe('Details');

    $this->actingAs($owner)
        ->patch(route('teams.projects.tasks.update', [$team, $project, $task]), [
            'title' => 'Ship it v2',
            'description' => null,
            'assignee_id' => null,
        ])
        ->assertRedirect(route('teams.projects.board', [$team, $project]));

    expect($task->fresh()->title)->toBe('Ship it v2');

    $this->actingAs($owner)
        ->post(route('teams.projects.tasks.move', [$team, $project, $task]), [
            'target_column_id' => $columnB->id,
        ])
        ->assertRedirect(route('teams.projects.board', [$team, $project]));

    expect($task->fresh()->column_id)->toBe($columnB->id);

    $this->actingAs($owner)
        ->delete(route('teams.projects.tasks.destroy', [$team, $project, $task]))
        ->assertRedirect(route('teams.projects.board', [$team, $project]));

    expect(Task::query()->whereKey($task->id)->exists())->toBeFalse();
});

it('blocks task mutations when the project is archived', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->archived()->create();
    $columnA = Column::factory()->forProject($project)->atPosition(0)->create();
    $columnB = Column::factory()->forProject($project)->atPosition(1)->create();
    $task = Task::factory()->forColumn($columnA)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->post(route('teams.projects.columns.tasks.store', [$team, $project, $columnA]), [
            'title' => 'Blocked',
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->patch(route('teams.projects.tasks.update', [$team, $project, $task]), [
            'title' => 'Blocked update',
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('teams.projects.tasks.move', [$team, $project, $task]), [
            'target_column_id' => $columnB->id,
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->delete(route('teams.projects.tasks.destroy', [$team, $project, $task]))
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
        ->patch(route('teams.projects.tasks.update', [$team, $projectA, $taskOnB]), [
            'title' => 'X',
        ])
        ->assertNotFound();
});

it('validates task title on update', function () {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createTeam($owner, ['name' => 'T']);
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create(['title' => 'Ok']);

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->from(route('teams.projects.board', [$team, $project]))
        ->patch(route('teams.projects.tasks.update', [$team, $project, $task]), [
            'title' => '',
            'description' => null,
            'assignee_id' => null,
        ])
        ->assertRedirect(route('teams.projects.board', [$team, $project]))
        ->assertSessionHasErrors('title');
});

it('validates assignee must belong to the project team', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $team = app(TeamService::class)->createTeam($owner, ['name' => 'T']);
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->from(route('teams.projects.board', [$team, $project]))
        ->post(route('teams.projects.columns.tasks.store', [$team, $project, $column]), [
            'title' => 'A',
            'assignee_id' => $outsider->id,
        ])
        ->assertRedirect(route('teams.projects.board', [$team, $project]))
        ->assertSessionHasErrors('assignee_id');
});

it('rejects move when the target column is not in the project', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $projectA = Project::factory()->forTeam($team)->create();
    $projectB = Project::factory()->forTeam($team)->create();
    $columnA = Column::factory()->forProject($projectA)->create();
    $columnB = Column::factory()->forProject($projectB)->create();
    $task = Task::factory()->forColumn($columnA)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->from(route('teams.projects.board', [$team, $projectA]))
        ->post(route('teams.projects.tasks.move', [$team, $projectA, $task]), [
            'target_column_id' => $columnB->id,
        ])
        ->assertRedirect(route('teams.projects.board', [$team, $projectA]))
        ->assertSessionHasErrors('target_column_id');
});
