<?php

namespace App\Repositories;

use App\Models\Label;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

class LabelRepository
{
    /**
     * @return Collection<int, Label>
     */
    public function listForProject(Project $project): Collection
    {
        return Label::query()
            ->where('project_id', $project->id)
            ->orderBy('name')
            ->get();
    }

    public function create(Project $project, string $name, ?string $color = null): Label
    {
        return Label::query()->create([
            'project_id' => $project->id,
            'name' => $name,
            'color' => $color,
        ]);
    }

    public function update(Label $label, string $name, ?string $color = null): Label
    {
        $label->update([
            'name' => $name,
            'color' => $color,
        ]);

        return $label->refresh();
    }

    public function delete(Label $label): void
    {
        $label->delete();
    }

    public function findForProject(Project $project, int $labelId): ?Label
    {
        return Label::query()
            ->where('project_id', $project->id)
            ->whereKey($labelId)
            ->first();
    }
}
