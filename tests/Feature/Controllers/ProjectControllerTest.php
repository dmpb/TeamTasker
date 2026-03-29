<?php

use App\Models\Project;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\TeamService;
use Tests\TestCase;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('redirects guests from team projects routes', function () {
    $team = Team::factory()->create();
    $project = Project::factory()->forTeam($team)->create();

    /** @var TestCase $this */
    $this->get(route('teams.projects.index', $team))->assertRedirect(route('login'));
    $this->post(route('teams.projects.store', $team), ['name' => 'X'])->assertRedirect(route('login'));
    $this->patch(route('teams.projects.update', [$team, $project]), [
        'name' => 'Y',
    ])->assertRedirect(route('login'));
    $this->post(route('teams.projects.archive', [$team, $project]))->assertRedirect(route('login'));
    $this->post(route('teams.projects.unarchive', [$team, $project]))->assertRedirect(route('login'));
    $this->delete(route('teams.projects.destroy', [$team, $project]))->assertRedirect(route('login'));
});

it('forbids team projects index for users outside the team', function () {
    $outsider = User::factory()->create();
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();

    /** @var TestCase $this */
    $this->actingAs($outsider)
        ->get(route('teams.projects.index', $team))
        ->assertForbidden();
});

it('shows projects for a team member with inertia', function () {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createTeam($owner, ['name' => 'Acme']);
    $project = Project::factory()->forTeam($team)->create(['name' => 'Board']);

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->get(route('teams.projects.index', $team))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('teams/projects/index')
            ->where('team.name', 'Acme')
            ->where('can.manageProjects', true)
            ->has('projects', 1)
            ->where('projects.0.name', 'Board'));
});

it('allows plain members to list projects but not create them', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($member)->create();

    /** @var TestCase $this */
    $this->actingAs($member)
        ->get(route('teams.projects.index', $team))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can.manageProjects', false));

    $this->actingAs($member)
        ->from(route('teams.projects.index', $team))
        ->post(route('teams.projects.store', $team), ['name' => 'Nope'])
        ->assertForbidden();

    $project = Project::factory()->forTeam($team)->create(['name' => 'P']);

    $this->actingAs($member)
        ->patch(route('teams.projects.update', [$team, $project]), [
            'name' => 'Hijack',
        ])
        ->assertForbidden();
});

it('lets owners create update archive unarchive and delete projects', function () {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createTeam($owner, ['name' => 'Ship']);

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->from(route('teams.projects.index', $team))
        ->post(route('teams.projects.store', $team), ['name' => 'MVP'])
        ->assertRedirect(route('teams.projects.index', $team));

    $project = Project::query()->where('team_id', $team->id)->first();
    expect($project)->not->toBeNull()->and($project->name)->toBe('MVP');

    $this->actingAs($owner)
        ->from(route('teams.projects.index', $team))
        ->patch(route('teams.projects.update', [$team, $project]), [
            'name' => 'MVP 2',
        ])
        ->assertRedirect(route('teams.projects.index', $team));

    expect($project->fresh()->name)->toBe('MVP 2');

    $this->actingAs($owner)
        ->post(route('teams.projects.archive', [$team, $project]))
        ->assertRedirect(route('teams.projects.index', $team));

    expect($project->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($owner)
        ->post(route('teams.projects.unarchive', [$team, $project]))
        ->assertRedirect(route('teams.projects.index', $team));

    expect($project->fresh()->archived_at)->toBeNull();

    $this->actingAs($owner)
        ->delete(route('teams.projects.destroy', [$team, $project]))
        ->assertRedirect(route('teams.projects.index', $team));

    expect(Project::query()->whereKey($project->id)->exists())->toBeFalse();
});

it('lets team admins manage projects', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($admin)->admin()->create();

    /** @var TestCase $this */
    $this->actingAs($admin)
        ->post(route('teams.projects.store', $team), ['name' => 'From admin'])
        ->assertRedirect(route('teams.projects.index', $team));

    expect(Project::query()->where('name', 'From admin')->exists())->toBeTrue();
});

it('returns 404 when the project belongs to another team', function () {
    $owner = User::factory()->create();
    $teamA = Team::factory()->forOwner($owner)->create();
    $teamB = Team::factory()->forOwner($owner)->create();
    $projectOnB = Project::factory()->forTeam($teamB)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->patch(route('teams.projects.update', [$teamA, $projectOnB]), [
            'name' => 'Hijack',
        ])
        ->assertNotFound();
});

it('ignores include_archived for members without manage permission', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($member)->create();
    Project::factory()->forTeam($team)->archived()->create(['name' => 'Old']);

    /** @var TestCase $this */
    $this->actingAs($member)
        ->get(route('teams.projects.index', [
            'team' => $team,
            'include_archived' => 1,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('projects', 0));
});

it('shows archived projects for managers when include_archived is set', function () {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createTeam($owner, ['name' => 'T']);
    Project::factory()->forTeam($team)->archived()->create(['name' => 'Z']);

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->get(route('teams.projects.index', [
            'team' => $team,
            'include_archived' => 1,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can.showArchived', true)
            ->has('projects', 1));
});

it('validates project name on store', function () {
    $owner = User::factory()->create();
    $team = app(TeamService::class)->createTeam($owner, ['name' => 'T']);

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->from(route('teams.projects.index', $team))
        ->post(route('teams.projects.store', $team), ['name' => ''])
        ->assertRedirect(route('teams.projects.index', $team))
        ->assertSessionHasErrors('name');
});
