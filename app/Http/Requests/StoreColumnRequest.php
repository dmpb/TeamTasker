<?php

namespace App\Http\Requests;

use App\Models\Column;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreColumnRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Team|null $team */
        $team = $this->route('team');
        /** @var Project|null $project */
        $project = $this->route('project');

        if (! $team instanceof Team || ! $project instanceof Project) {
            return false;
        }

        return $project->team_id === $team->id
            && $this->user()?->can('create', [Column::class, $project]) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filter_column' => ['sometimes', 'nullable', 'integer'],
            'filter_assignee' => ['sometimes', 'nullable', 'integer'],
            'filter_label' => ['sometimes', 'nullable', 'integer'],
            'filter_priority' => ['sometimes', 'nullable', 'string', 'max:20'],
            'filter_due' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
