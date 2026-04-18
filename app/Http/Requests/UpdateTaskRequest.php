<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTaskRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('assignee_id') && $this->input('assignee_id') === '') {
            $this->merge(['assignee_id' => null]);
        }

        if ($this->has('due_date') && $this->input('due_date') === '') {
            $this->merge(['due_date' => null]);
        }

        if ($this->has('priority') && $this->input('priority') === '') {
            $this->merge(['priority' => null]);
        }
    }

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

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'clear_due_date' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'nullable', 'string', Rule::enum(TaskPriority::class)],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['integer', Rule::exists('labels', 'id')->where('project_id', $project->id)],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filter_column' => ['sometimes', 'nullable', 'integer'],
            'filter_assignee' => ['sometimes', 'nullable', 'integer'],
            'filter_label' => ['sometimes', 'nullable', 'integer'],
            'filter_priority' => ['sometimes', 'nullable', 'string', 'max:20'],
            'filter_due' => ['sometimes', 'nullable', 'string', 'max:32'],
            'return_to_task' => ['sometimes', 'boolean'],
            'sync_label_ids' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $assigneeId = $this->input('assignee_id');
            if ($assigneeId === null || $assigneeId === '') {
                return;
            }

            /** @var Project $project */
            $project = $this->route('project');

            if (! $this->assigneeBelongsToProjectTeam((int) $assigneeId, $project)) {
                $validator->errors()->add('assignee_id', 'The assignee must be a member of this team.');
            }
        });
    }

    private function assigneeBelongsToProjectTeam(int $userId, Project $project): bool
    {
        $team = $project->team;

        if ($team->owner_id === $userId) {
            return true;
        }

        return TeamMember::query()
            ->where('team_id', $team->id)
            ->where('user_id', $userId)
            ->exists();
    }
}
