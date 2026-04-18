<?php

use App\Models\Column;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Tests\TestCase;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('redirects guests from board task sync', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->atPosition(0)->create();

    /** @var TestCase $this */
    $this->post(route('teams.projects.board.tasks.sync', [$team, $project]), [
        'columns' => [
            ['column_id' => $column->id, 'task_ids' => []],
        ],
    ])->assertRedirect(route('login'));
});

it('forbids board sync for plain team members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    TeamMember::factory()->forTeam($team)->forUser($member)->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->atPosition(0)->create();

    /** @var TestCase $this */
    $this->actingAs($member)
        ->post(route('teams.projects.board.tasks.sync', [$team, $project]), [
            'columns' => [
                ['column_id' => $column->id, 'task_ids' => []],
            ],
        ])
        ->assertForbidden();
});

it('syncs task order and columns for a project owner', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->create();
    $colA = Column::factory()->forProject($project)->atPosition(0)->create();
    $colB = Column::factory()->forProject($project)->atPosition(1)->create();
    $t1 = Task::factory()->forColumn($colA)->atPosition(0)->create();
    $t2 = Task::factory()->forColumn($colA)->atPosition(1)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->from(route('teams.projects.board', [$team, $project]))
        ->post(route('teams.projects.board.tasks.sync', [$team, $project]), [
            'columns' => [
                ['column_id' => $colA->id, 'task_ids' => [(int) $t2->id, (int) $t1->id]],
                ['column_id' => $colB->id, 'task_ids' => []],
            ],
        ])
        ->assertRedirect(route('teams.projects.board', [$team, $project]))
        ->assertSessionHas('success');

    expect(
        Task::query()
            ->where('column_id', $colA->id)
            ->orderBy('position')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all(),
    )->toBe([(int) $t2->id, (int) $t1->id]);
});

it('rejects board sync when a column is missing from the payload', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->create();
    $colA = Column::factory()->forProject($project)->atPosition(0)->create();
    $colB = Column::factory()->forProject($project)->atPosition(1)->create();
    $t1 = Task::factory()->forColumn($colA)->atPosition(0)->create();

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->from(route('teams.projects.board', [$team, $project]))
        ->post(route('teams.projects.board.tasks.sync', [$team, $project]), [
            'columns' => [
                ['column_id' => $colA->id, 'task_ids' => [(int) $t1->id]],
            ],
        ])
        ->assertRedirect(route('teams.projects.board', [$team, $project]))
        ->assertSessionHasErrors('board');
});
