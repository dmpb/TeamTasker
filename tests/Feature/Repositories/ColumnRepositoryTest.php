<?php

use App\Models\Column;
use App\Models\Project;
use App\Repositories\ColumnRepository;

it('lists columns for a project ordered by position then id', function () {
    $project = Project::factory()->create();
    $repository = app(ColumnRepository::class);

    Column::factory()->forProject($project)->create(['name' => 'Third', 'position' => 2]);
    Column::factory()->forProject($project)->create(['name' => 'First', 'position' => 0]);
    Column::factory()->forProject($project)->create(['name' => 'Second', 'position' => 1]);

    $list = $repository->listColumnsForProject($project);

    expect($list->pluck('name')->all())->toBe(['First', 'Second', 'Third']);
});

it('appends new column at the next position when position is omitted', function () {
    $project = Project::factory()->create();
    $repository = app(ColumnRepository::class);

    $a = $repository->createColumn($project, 'A');
    $b = $repository->createColumn($project, 'B');

    expect($a->position)->toBe(0)
        ->and($b->position)->toBe(1);
});

it('creates a column at an explicit position', function () {
    $project = Project::factory()->create();
    $repository = app(ColumnRepository::class);

    $column = $repository->createColumn($project, 'Custom', 10);

    expect($column->position)->toBe(10)
        ->and($column->project_id)->toBe($project->id);
});

it('updates a column name', function () {
    $project = Project::factory()->create();
    $repository = app(ColumnRepository::class);
    $column = Column::factory()->forProject($project)->create(['name' => 'Todo']);

    $updated = $repository->updateColumn($column, 'Backlog');

    expect($updated->name)->toBe('Backlog');
});

it('reorders columns in a transaction', function () {
    $project = Project::factory()->create();
    $repository = app(ColumnRepository::class);
    $c0 = Column::factory()->forProject($project)->atPosition(0)->create(['name' => 'A']);
    $c1 = Column::factory()->forProject($project)->atPosition(1)->create(['name' => 'B']);
    $c2 = Column::factory()->forProject($project)->atPosition(2)->create(['name' => 'C']);

    $repository->reorderColumnsForProject($project, [$c2->id, $c0->id, $c1->id]);

    $fresh = $repository->listColumnsForProject($project);

    expect($fresh->pluck('name')->all())->toBe(['C', 'A', 'B'])
        ->and($fresh->pluck('position')->all())->toBe([0, 1, 2]);
});

it('throws when reorder payload omits a project column', function () {
    $project = Project::factory()->create();
    $repository = app(ColumnRepository::class);
    $c0 = Column::factory()->forProject($project)->atPosition(0)->create();
    Column::factory()->forProject($project)->atPosition(1)->create();

    expect(fn () => $repository->reorderColumnsForProject($project, [$c0->id]))
        ->toThrow(InvalidArgumentException::class);
});

it('throws when reorder payload contains duplicate ids', function () {
    $project = Project::factory()->create();
    $repository = app(ColumnRepository::class);
    $c0 = Column::factory()->forProject($project)->atPosition(0)->create();

    expect(fn () => $repository->reorderColumnsForProject($project, [$c0->id, $c0->id]))
        ->toThrow(InvalidArgumentException::class);
});

it('throws when reorder references a column from another project', function () {
    $p1 = Project::factory()->create();
    $p2 = Project::factory()->create();
    $repository = app(ColumnRepository::class);
    $a = Column::factory()->forProject($p1)->atPosition(0)->create();
    Column::factory()->forProject($p1)->atPosition(1)->create();
    $other = Column::factory()->forProject($p2)->atPosition(0)->create();

    expect(fn () => $repository->reorderColumnsForProject($p1, [$a->id, $other->id]))
        ->toThrow(InvalidArgumentException::class);
});

it('deletes a column', function () {
    $project = Project::factory()->create();
    $repository = app(ColumnRepository::class);
    $column = Column::factory()->forProject($project)->create();

    $repository->deleteColumn($column);

    expect(Column::query()->whereKey($column->id)->exists())->toBeFalse();
});

it('cascades column deletion when the project is deleted', function () {
    $project = Project::factory()->create();
    $column = Column::factory()->forProject($project)->create();

    $project->delete();

    expect(Column::query()->whereKey($column->id)->exists())->toBeFalse();
});
