<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Team|null $team */
        $team = $this->route('team');
        /** @var Project|null $project */
        $project = $this->route('project');
        /** @var Task|null $task */
        $task = $this->route('task');
        /** @var TaskChecklistItem|null $checklistItem */
        $checklistItem = $this->route('checklistItem');

        if (! $team instanceof Team || ! $project instanceof Project || ! $task instanceof Task || ! $checklistItem instanceof TaskChecklistItem) {
            return false;
        }

        return $project->team_id === $team->id
            && $task->project_id === $project->id
            && (int) $checklistItem->task_id === (int) $task->id
            && $this->user()?->can('update', $task) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'is_completed' => ['required', 'boolean'],
        ];
    }
}
