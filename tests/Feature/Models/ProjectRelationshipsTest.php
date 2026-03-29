<?php

use App\Models\Project;
use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

it('defines project team relationship', function () {
    $project = new Project;

    expect($project->team())->toBeInstanceOf(BelongsTo::class);
});

it('defines team projects relationship', function () {
    $team = new Team;

    expect($team->projects())->toBeInstanceOf(HasMany::class);
});
