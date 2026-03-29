<?php

namespace App\Services;

use App\Models\Column;
use App\Models\Project;
use App\Repositories\ColumnRepository;
use Illuminate\Database\Eloquent\Collection;

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

    public function updateColumn(Column $column, string $name): Column
    {
        return $this->columnRepository->updateColumn($column, $name);
    }

    public function deleteColumn(Column $column): void
    {
        $this->columnRepository->deleteColumn($column);
    }

    /**
     * @param  list<int|string>  $columnIdsOrdered
     */
    public function reorderColumns(Project $project, array $columnIdsOrdered): void
    {
        $this->columnRepository->reorderColumnsForProject($project, $columnIdsOrdered);
    }
}
