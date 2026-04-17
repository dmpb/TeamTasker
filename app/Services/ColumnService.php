<?php

namespace App\Services;

use App\Models\Column;
use App\Models\Project;
use App\Repositories\ColumnRepository;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class ColumnService
{
    public function __construct(public ColumnRepository $columnRepository) {}

    /**
     * @return Collection<int, Column>
     */
    public function listProjectColumns(Project $project): Collection
    {
        return $this->columnRepository->listColumnsForProject($project);
    }

    public function createColumn(Project $project, string $name, ?int $position = null): Column
    {
        return $this->columnRepository->createColumn($project, $name, $position);
    }

    public function updateColumn(Project $project, Column $column, string $name): Column
    {
        $this->assertColumnBelongsToProject($column, $project);

        return $this->columnRepository->updateColumn($column, $name);
    }

    public function deleteColumn(Project $project, Column $column): void
    {
        $this->assertColumnBelongsToProject($column, $project);

        $this->columnRepository->deleteColumn($column);
    }

    /**
     * @param  list<int|string>  $columnIdsOrdered
     */
    public function reorderColumns(Project $project, array $columnIdsOrdered): void
    {
        $this->columnRepository->reorderColumnsForProject($project, $columnIdsOrdered);
    }

    private function assertColumnBelongsToProject(Column $column, Project $project): void
    {
        if ($column->project_id !== $project->id) {
            throw new InvalidArgumentException('Column does not belong to the given project.');
        }
    }
}
