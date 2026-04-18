<?php

use App\Models\Column;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\ActivityLogService;
use Tests\TestCase;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('redirects guests from project activity route', function () {
    $team = Team::factory()->create();
    $project = Project::factory()->forTeam($team)->create();

    /** @var TestCase $this */
    $this->get(route('teams.projects.activity.index', [$team, $project]))
        ->assertRedirect(route('login'));
});

it('forbids users outside the project team', function () {
    $outsider = User::factory()->create();
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->create();

    /** @var TestCase $this */
    $this->actingAs($outsider)
        ->get(route('teams.projects.activity.index', [$team, $project]))
        ->assertForbidden();
});

it('shows project activity logs for team members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($member)->create();
    $project = Project::factory()->forTeam($team)->create();
    $otherProject = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $otherColumn = Column::factory()->forProject($otherProject)->create();
    $task = Task::factory()->forColumn($column)->create();
    $otherTask = Task::factory()->forColumn($otherColumn)->create();
    $activityService = app(ActivityLogService::class);
    $activityService->recordTaskCreated($task, $owner);
    $activityService->recordTaskCreated($otherTask, $owner);

    /** @var TestCase $this */
    $this->actingAs($member)
        ->get(route('teams.projects.activity.index', [$team, $project]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('teams/projects/activity/index')
            ->where('project.id', $project->id)
            ->has('activityLogs', 1)
            ->where('activityLogs.0.event', 'task.created')
            ->where('activityLogs.0.actor.id', $owner->id)
            ->has('actors')
            ->has('filters')
            ->where('filters.event', '')
            ->where('filters.actor_id', null)
            ->where('filters.q', '')
            ->where('can.exportActivityLog', false));
});

it('applies activity event filter from query string', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($member)->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create();
    $activityService = app(ActivityLogService::class);
    $activityService->recordTaskCreated($task, $owner);
    $activityService->recordTaskDeleted($task, $owner);

    /** @var TestCase $this */
    $this->actingAs($member)
        ->get(route('teams.projects.activity.index', [$team, $project, 'event' => 'task.created']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activityLogs', 1)
            ->where('activityLogs.0.event', 'task.created')
            ->where('filters.event', 'task.created'));
});

it('shows export permission for team owners on activity page', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->get(route('teams.projects.activity.index', [$team, $project]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can.exportActivityLog', true));
});

it('returns 404 when the project belongs to another team', function () {
    $owner = User::factory()->create();
    $teamA = Team::factory()->forOwner($owner)->create();
    $teamB = Team::factory()->forOwner($owner)->create();
    $projectOnB = Project::factory()->forTeam($teamB)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->get(route('teams.projects.activity.index', [$teamA, $projectOnB]))
        ->assertNotFound();
});
