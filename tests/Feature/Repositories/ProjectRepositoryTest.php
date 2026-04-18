<?php

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Repositories\ProjectRepository;

it('lists active projects for a team ordered by id desc', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->forOwner($owner)->create();
    $repository = app(ProjectRepository::class);

    $older = Project::factory()->forTeam($team)->create(['name' => 'Older']);
    $newer = Project::factory()->forTeam($team)->create(['name' => 'Newer']);
    Project::factory()->forTeam($team)->archived()->create(['name' => 'Archived']);

    $list = $repository->paginateProjectsForTeam($team)->getCollection();

    expect($list)->toHaveCount(2)
        ->and($list->first()->is($newer))->toBeTrue()
        ->and($list->last()->is($older))->toBeTrue();
});

it('can include archived projects when listing', function () {
    $team = Team::factory()->create();
    $repository = app(ProjectRepository::class);
    $active = Project::factory()->forTeam($team)->create(['name' => 'Active']);
    $archived = Project::factory()->forTeam($team)->archived()->create(['name' => 'Old']);

    $all = $repository->paginateProjectsForTeam($team, archiveScope: 'all')->getCollection();

    expect($all->pluck('id')->all())->toContain($active->id, $archived->id);
});

it('creates updates archives restores and deletes a project', function () {
    $team = Team::factory()->create();
    $repository = app(ProjectRepository::class);

    $project = $repository->createProject($team, 'Roadmap');

    expect($project->team_id)->toBe($team->id)
        ->and($project->name)->toBe('Roadmap')
        ->and($project->archived_at)->toBeNull();

    $repository->updateProject($project, 'Roadmap v2');
    expect($project->fresh()->name)->toBe('Roadmap v2');

    $repository->archiveProject($project);
    expect($project->fresh()->archived_at)->not->toBeNull();

    $repository->restoreProject($project);
    expect($project->fresh()->archived_at)->toBeNull();

    $repository->deleteProject($project);
    expect(Project::query()->whereKey($project->id)->exists())->toBeFalse();
});

it('cascades project deletion when the team is deleted', function () {
    $team = Team::factory()->create();
    $project = Project::factory()->forTeam($team)->create();

    $team->delete();

    expect(Project::query()->whereKey($project->id)->exists())->toBeFalse();
});
