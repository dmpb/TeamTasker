<?php

use App\Models\Column;
use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\Team;
use App\Models\User;
use Tests\TestCase;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('redirects guests from task show', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create();

    /** @var TestCase $this */
    $this->get(route('teams.projects.tasks.show', [$team, $project, $task]))
        ->assertRedirect(route('login'));
});

it('shows task detail for team members', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $task = Task::factory()->forColumn($column)->create(['title' => 'Alpha task']);

    Label::query()->create([
        'project_id' => $project->id,
        'name' => 'Bug',
        'color' => '#ef4444',
    ]);

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->get(route('teams.projects.tasks.show', [$team, $project, $task]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('teams/projects/tasks/show')
            ->where('task.title', 'Alpha task')
            ->has('labels', 1)
            ->has('assignableUsers'));
});

it('blocks completing a task when a prerequisite is incomplete', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $project = Project::factory()->forTeam($team)->create();
    $column = Column::factory()->forProject($project)->create();
    $prereq = Task::factory()->forColumn($column)->atPosition(0)->create(['title' => 'First']);
    $dependent = Task::factory()->forColumn($column)->atPosition(1)->create(['title' => 'Second']);

    TaskDependency::query()->create([
        'dependent_task_id' => $dependent->id,
        'prerequisite_task_id' => $prereq->id,
    ]);

    /** @var TestCase $this */
    $this->actingAs($owner)
        ->from(route('teams.projects.tasks.show', [$team, $project, $dependent]))
        ->post(route('teams.projects.tasks.complete', [$team, $project, $dependent]))
        ->assertRedirect(route('teams.projects.tasks.show', [$team, $project, $dependent]))
        ->assertSessionHasErrors('complete');

    expect($dependent->fresh()->isCompleted())->toBeFalse();
});
