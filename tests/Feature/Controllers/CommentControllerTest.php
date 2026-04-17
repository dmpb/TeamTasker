<?php

use App\Models\Column;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Tests\TestCase;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('redirects guests from task comments index', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create();

    /** @var TestCase $this */
    $this->get(route('teams.projects.tasks.comments.index', [$team, $project, $task]))
        ->assertRedirect(route('login'));
});

it('shows task comments for team members with filter defaults', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create();
    Comment::factory()->forTask($task)->byUser($owner)->create(['body' => 'Hello world']);

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->get(route('teams.projects.tasks.comments.index', [$team, $project, $task]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('teams/projects/tasks/comments')
            ->has('comments', 1)
            ->where('comments.0.body', 'Hello world')
            ->has('filters')
            ->where('filters.q', ''));
});

it('filters comments by body using the q query parameter', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create();
    Comment::factory()->forTask($task)->byUser($owner)->create(['body' => 'Unique needle phrase']);
    Comment::factory()->forTask($task)->byUser($owner)->create(['body' => 'Other text']);

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->get(route('teams.projects.tasks.comments.index', [$team, $project, $task, 'q' => 'needle']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('comments', 1)
            ->where('comments.0.body', 'Unique needle phrase')
            ->where('filters.q', 'needle'));
});
