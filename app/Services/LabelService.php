<?php

namespace App\Services;

use App\Models\Label;
use App\Models\Project;
use App\Repositories\LabelRepository;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class LabelService
{
    public function __construct(protected LabelRepository $labelRepository) {}

    /**
     * @return Collection<int, Label>
     */
    public function listProjectLabels(Project $project): Collection
    {
        return $this->labelRepository->listForProject($project);
    }

    public function createLabel(Project $project, string $name, ?string $color = null): Label
    {
        $normalized = trim($name);

        if ($normalized === '') {
            throw new InvalidArgumentException(__('Label name is required.'));
        }

        return $this->labelRepository->create($project, $normalized, $color);
    }

    public function updateLabel(Project $project, Label $label, string $name, ?string $color = null): Label
    {
        $this->assertLabelBelongsToProject($label, $project);

        $normalized = trim($name);

        if ($normalized === '') {
            throw new InvalidArgumentException(__('Label name is required.'));
        }

        return $this->labelRepository->update($label, $normalized, $color);
    }

    public function deleteLabel(Project $project, Label $label): void
    {
        $this->assertLabelBelongsToProject($label, $project);
        $this->labelRepository->delete($label);
    }

    private function assertLabelBelongsToProject(Label $label, Project $project): void
    {
        if ((int) $label->project_id !== (int) $project->id) {
            throw new InvalidArgumentException(__('Label does not belong to this project.'));
        }
    }
}
