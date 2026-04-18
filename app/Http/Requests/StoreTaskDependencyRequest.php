<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskDependencyRequest extends FormRequest
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
        /** @var Project $project */
        $project = $this->route('project');
        /** @var Task $task */
        $task = $this->route('task');

        return [
            'prerequisite_task_id' => [
                'required',
                'integer',
                Rule::notIn([(int) $task->id]),
                Rule::exists('tasks', 'id')->where('project_id', $project->id),
            ],
        ];
    }
}
