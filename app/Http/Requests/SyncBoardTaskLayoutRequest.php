<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncBoardTaskLayoutRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $columns = $this->input('columns');
        if (! is_array($columns)) {
            return;
        }

        foreach ($columns as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            if (! array_key_exists('task_ids', $row) || $row['task_ids'] === null) {
                $columns[$index]['task_ids'] = [];
            }
        }

        $this->merge(['columns' => $columns]);
    }

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
            && $this->user()?->can('update', $project) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'columns' => ['required', 'array'],
            'columns.*.column_id' => [
                'required',
                'integer',
                Rule::exists('board_columns', 'id')->where('project_id', $project->id),
            ],
            'columns.*.task_ids' => ['present', 'array'],
            'columns.*.task_ids.*' => [
                'integer',
                Rule::exists('tasks', 'id')->where('project_id', $project->id),
            ],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filter_column' => ['sometimes', 'nullable', 'integer'],
            'filter_assignee' => ['sometimes', 'nullable', 'integer'],
            'filter_label' => ['sometimes', 'nullable', 'integer'],
            'filter_priority' => ['sometimes', 'nullable', 'string', 'max:20'],
            'filter_due' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return list<array{ column_id: int, task_ids: list<int> }>
     */
    public function layoutPayload(): array
    {
        /** @var array{ columns: list<array{ column_id: int|string, task_ids: list<int|string> }> } $validated */
        $validated = $this->validated();

        return collect($validated['columns'])
            ->map(static function (array $row): array {
                return [
                    'column_id' => (int) $row['column_id'],
                    'task_ids' => array_values(array_map(static fn (mixed $id): int => (int) $id, $row['task_ids'])),
                ];
            })
            ->values()
            ->all();
    }
}
