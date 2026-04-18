<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLabelRequest;
use App\Http\Requests\UpdateLabelRequest;
use App\Models\Label;
use App\Models\Project;
use App\Models\Team;
use App\Services\LabelService;
use Illuminate\Http\RedirectResponse;

class LabelController extends Controller
{
    public function __construct(protected LabelService $labelService) {}

    public function store(StoreLabelRequest $request, Team $team, Project $project): RedirectResponse
    {
        /** @var array{ name: string, color?: string|null } $validated */
        $validated = $request->validated();

        $this->labelService->createLabel($project, $validated['name'], $validated['color'] ?? null);

        return redirect()->to(route('teams.projects.board', [$team, $project], false))
            ->with('success', __('Label created.'));
    }

    public function update(UpdateLabelRequest $request, Team $team, Project $project, Label $label): RedirectResponse
    {
        /** @var array{ name: string, color?: string|null } $validated */
        $validated = $request->validated();

        $this->labelService->updateLabel($project, $label, $validated['name'], $validated['color'] ?? null);

        return redirect()->to(route('teams.projects.board', [$team, $project], false))
            ->with('success', __('Label updated.'));
    }

    public function destroy(Team $team, Project $project, Label $label): RedirectResponse
    {
        $this->authorize('update', $project);

        if ((int) $label->project_id !== (int) $project->id) {
            abort(404);
        }

        $this->labelService->deleteLabel($project, $label);

        return redirect()->to(route('teams.projects.board', [$team, $project], false))
            ->with('success', __('Label deleted.'));
    }
}
