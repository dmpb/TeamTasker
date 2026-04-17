<?php

namespace App\Http\Requests;

use App\Models\Column;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateColumnRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Team|null $team */
        $team = $this->route('team');
        /** @var Project|null $project */
        $project = $this->route('project');
        /** @var Column|null $column */
        $column = $this->route('column');

        if (! $team instanceof Team || ! $project instanceof Project || ! $column instanceof Column) {
            return false;
        }

        return $project->team_id === $team->id
            && $column->project_id === $project->id
            && $this->user()?->can('update', $column) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filter_column' => ['sometimes', 'nullable', 'integer'],
            'filter_assignee' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
