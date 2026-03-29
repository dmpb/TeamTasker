<?php

namespace App\Http\Requests;

use App\Models\Column;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MoveTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Team|null $team */
        $team = $this->route('team');
        /** @var Project|null $project */
        $project = $this->route('project');
        /** @var Task|null $task */
        $task = $this->route('task');

        if (! $team instanceof Team || ! $project instanceof Project || ! $task instanceof Task) {
            return false;
        }

        return $project->team_id === $team->id
            && $task->project_id === $project->id
            && $this->user()?->can('update', $task) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_column_id' => ['required', 'integer', 'exists:board_columns,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Project $project */
            $project = $this->route('project');
            $targetId = (int) $this->input('target_column_id');
            $column = Column::query()->find($targetId);

            if ($column === null || $column->project_id !== $project->id) {
                $validator->errors()->add('target_column_id', 'The target column must belong to this project.');
            }
        });
    }
}
