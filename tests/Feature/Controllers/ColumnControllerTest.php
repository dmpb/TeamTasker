<?php

use App\Models\Column;
use App\Models\Project;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\TeamService;
use Tests\TestCase;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('redirects guests from project column routes', function () {
    $team = Team::factory()->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();

    /** @var TestCase $this */
    $this->get(route('teams.projects.board', [$team, $project]))->assertRedirect(route('login'));
    $this->post(route('teams.projects.columns.store', [$team, $project]), [
        'name' => 'Todo',
    ])->assertRedirect(route('login'));
    $this->patch(route('teams.projects.columns.update', [$team, $project, $column]), [
        'name' => 'Done',
    ])->assertRedirect(route('login'));
    $this->delete(route('teams.projects.columns.destroy', [$team, $project, $column]))->assertRedirect(route('login'));
    $this->post(route('teams.projects.columns.reorder', [$team, $project]), [
        'column_ids' => [$column->id],
    ])->assertRedirect(route('login'));
});

it('forbids the board for users outside the team', function () {
    $outsider = User::factory()->create();
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->create();

    /** @var TestCase $this */
    $this->actingAs($outsider)
        ->get(route('teams.projects.board', [$team, $project]))
        ->assertForbidden();
});

it('shows the board with columns for a team member', function () {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createTeam($owner, ['name' => 'Acme']);
    $project = Project::factory()->forTeam($team)->create(['name' => 'Sprint']);
    Column::factory()->forProject($project)->atPosition(0)->create(['name' => 'Todo']);

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->get(route('teams.projects.board', [$team, $project]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('teams/projects/board')
            ->where('project.name', 'Sprint')
            ->where('can.manageColumns', true)
            ->has('columns', 1)
            ->where('columns.0.name', 'Todo'));
});

it('allows plain members to view the board but not mutate columns', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($member)->create();
    $project = Project::factory()->forTeam($team)->create(['name' => 'P']);
    $column = Column::factory()->forProject($project)->create(['name' => 'A']);

    /** @var TestCase $this */
    $this->actingAs($member)
        ->get(route('teams.projects.board', [$team, $project]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can.manageColumns', false));

    $this->actingAs($member)
        ->from(route('teams.projects.board', [$team, $project]))
        ->post(route('teams.projects.columns.store', [$team, $project]), ['name' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($member)
        ->patch(route('teams.projects.columns.update', [$team, $project, $column]), [
            'name' => 'Hijack',
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('teams.projects.columns.destroy', [$team, $project, $column]))
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('teams.projects.columns.reorder', [$team, $project]), [
            'column_ids' => [$column->id],
        ])
        ->assertForbidden();
});

it('lets owners create update delete and reorder columns', function () {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createTeam($owner, ['name' => 'Ship']);
    $project = Project::factory()->forTeam($team)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->from(route('teams.projects.board', [$team, $project]))
        ->post(route('teams.projects.columns.store', [$team, $project]), ['name' => 'Todo'])
        ->assertRedirect(route('teams.projects.board', [$team, $project]));

    $colA = Column::query()->where('project_id', $project->id)->where('name', 'Todo')->first();
    expect($colA)->not->toBeNull();

    $this->actingAs($owner)
        ->post(route('teams.projects.columns.store', [$team, $project]), ['name' => 'Done'])
        ->assertRedirect(route('teams.projects.board', [$team, $project]));

    $colB = Column::query()->where('project_id', $project->id)->where('name', 'Done')->first();
    expect($colB)->not->toBeNull();

    $this->actingAs($owner)
        ->patch(route('teams.projects.columns.update', [$team, $project, $colA]), [
            'name' => 'Backlog',
        ])
        ->assertRedirect(route('teams.projects.board', [$team, $project]));

    expect($colA->fresh()->name)->toBe('Backlog');

    $this->actingAs($owner)
        ->post(route('teams.projects.columns.reorder', [$team, $project]), [
            'column_ids' => [$colB->id, $colA->id],
        ])
        ->assertRedirect(route('teams.projects.board', [$team, $project]));

    $ordered = Column::query()->where('project_id', $project->id)->orderBy('position')->pluck('name')->all();
    expect($ordered)->toBe(['Done', 'Backlog']);

    $this->actingAs($owner)
        ->delete(route('teams.projects.columns.destroy', [$team, $project, $colB]))
        ->assertRedirect(route('teams.projects.board', [$team, $project]));

    expect(Column::query()->whereKey($colB->id)->exists())->toBeFalse();
});

it('returns 404 when the project belongs to another team', function () {
    $owner = User::factory()->create();
    $teamA = Team::factory()->forOwner($owner)->create();
    $teamB = Team::factory()->forOwner($owner)->create();
    $projectOnB = Project::factory()->forTeam($teamB)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->get(route('teams.projects.board', [$teamA, $projectOnB]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->post(route('teams.projects.columns.store', [$teamA, $projectOnB]), ['name' => 'X'])
        ->assertNotFound();
});

it('returns 404 when the column does not belong to the project in the url', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $projectA = Project::factory()->forTeam($team)->create();
    $projectB = Project::factory()->forTeam($team)->create();
    $columnOnB = Column::factory()->forProject($projectB)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->patch(route('teams.projects.columns.update', [$team, $projectA, $columnOnB]), [
            'name' => 'X',
        ])
        ->assertNotFound();
});

it('validates column name on store', function () {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createTeam($owner, ['name' => 'T']);
    $project = Project::factory()->forTeam($team)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->from(route('teams.projects.board', [$team, $project]))
        ->post(route('teams.projects.columns.store', [$team, $project]), ['name' => ''])
        ->assertRedirect(route('teams.projects.board', [$team, $project]))
        ->assertSessionHasErrors('name');
});

it('validates reorder column ids against the project', function () {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createTeam($owner, ['name' => 'T']);
    $project = Project::factory()->forTeam($team)->create();
    $col = Column::factory()->forProject($project)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->from(route('teams.projects.board', [$team, $project]))
        ->post(route('teams.projects.columns.reorder', [$team, $project]), [
            'column_ids' => [$col->id, 999_999],
        ])
        ->assertRedirect(route('teams.projects.board', [$team, $project]))
        ->assertSessionHasErrors('column_ids');
});
