<?php

use App\Models\Project;
use App\Services\ColumnService;

it('lists creates updates and reorders columns through the service', function () {
    $project = Project::factory()->create();
    $service = app(ColumnService::class);

    $c0 = $service->createColumn($project, 'A');
    $c1 = $service->createColumn($project, 'B');

    expect($service->listProjectColumns($project)->pluck('name')->all())->toBe(['A', 'B']);

    $service->updateColumn($project, $c0, 'A1');
    expect($c0->fresh()->name)->toBe('A1');

    $service->reorderColumns($project, [$c1->id, $c0->id]);

    expect($service->listProjectColumns($project)->pluck('name')->all())->toBe(['B', 'A1']);

    $service->deleteColumn($project, $c1);

    expect($service->listProjectColumns($project)->pluck('name')->all())->toBe(['A1']);
});
