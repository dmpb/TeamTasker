<?php

namespace App\Repositories;

use App\Models\Column;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ColumnRepository
{
    public function nextPositionForProject(Project $project): int
    {
        $max = Column::query()->where('project_id', $project->id)->max('position');

        return $max === null ? 0 : (int) $max + 1;
    }

    /**
     * @return Collection<int, Column>
     */
    public function listColumnsForProject(Project $project): Collection
    {
        return Column::query()
            ->where('project_id', $project->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function createColumn(Project $project, string $name, ?int $position = null): Column
    {
        if ($position === null) {
            $position = $this->nextPositionForProject($project);
        }

        return Column::query()->create([
            'project_id' => $project->id,
            'name' => $name,
            'position' => $position,
        ]);
    }

    public function updateColumn(Column $column, string $name): Column
    {
        $column->update([
            'name' => $name,
        ]);

        return $column->refresh();
    }

    public function deleteColumn(Column $column): void
    {
        $column->delete();
    }

    /**
     * Reassigns zero-based positions in the given order. Uses a two-phase bump so
     * unique (project_id, position) is not violated mid-update.
     *
     * @param  list<int|string>  $columnIdsOrdered
     */
    public function reorderColumnsForProject(Project $project, array $columnIdsOrdered): void
    {
        DB::transaction(function () use ($project, $columnIdsOrdered): void {
            $orderedIds = array_map(static fn (int|string $id): int => (int) $id, array_values($columnIdsOrdered));

            if ($orderedIds !== array_values(array_unique($orderedIds))) {
                throw new InvalidArgumentException('Duplicate column ids in reorder payload.');
            }

            $existingIds = Column::query()
                ->where('project_id', $project->id)
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();

            $sortedPayload = $orderedIds;
            sort($sortedPayload);

            if ($existingIds !== $sortedPayload) {
                throw new InvalidArgumentException('Reorder must reference exactly the columns belonging to this project.');
            }

            $offset = 1_000_000;

            foreach ($orderedIds as $i => $id) {
                Column::query()->where('project_id', $project->id)->whereKey($id)->update(['position' => $offset + $i]);
            }

            foreach ($orderedIds as $i => $id) {
                Column::query()->whereKey($id)->update(['position' => $i]);
            }
        });
    }
}
