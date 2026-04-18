<?php

use App\Models\Column;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\ActivityLogService;
use Tests\TestCase;

it('forbids activity export for plain team members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($member)->create();
    $project = Project::factory()->forTeam($team)->create();

    /** @var TestCase $this */
    $this->actingAs($member)
        ->get(route('teams.projects.activity.export', [$team, $project]))
        ->assertForbidden();
});

it('streams csv for team owners', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create();
    app(ActivityLogService::class)->recordTaskCreated($task, $owner);

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->get(route('teams.projects.activity.export', [$team, $project]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
