<?php

namespace App\Http\Requests;

use App\Models\Label;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Team|null $team */
        $team = $this->route('team');
        /** @var Project|null $project */
        $project = $this->route('project');
        /** @var Label|null $label */
        $label = $this->route('label');

        if (! $team instanceof Team || ! $project instanceof Project || ! $label instanceof Label) {
            return false;
        }

        return $project->team_id === $team->id
            && (int) $label->project_id === (int) $project->id
            && $this->user()?->can('update', $project) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:32'],
        ];
    }
}
