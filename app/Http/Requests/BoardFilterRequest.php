<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BoardFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'filter_column' => $this->filled('filter_column') ? (int) $this->input('filter_column') : null,
            'filter_assignee' => $this->filled('filter_assignee') ? (int) $this->input('filter_assignee') : null,
            'search' => $this->filled('search') ? trim((string) $this->input('search')) : null,
            'filter_label' => $this->filled('filter_label') ? (int) $this->input('filter_label') : null,
            'filter_priority' => $this->filled('filter_priority') ? trim((string) $this->input('filter_priority')) : null,
            'filter_due' => $this->filled('filter_due') ? trim((string) $this->input('filter_due')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'filter_column' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('board_columns', 'id')->where('project_id', $project->id),
            ],
            'filter_assignee' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filter_label' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('labels', 'id')->where('project_id', $project->id),
            ],
            'filter_priority' => ['sometimes', 'nullable', 'string', Rule::in(['low', 'medium', 'high'])],
            'filter_due' => ['sometimes', 'nullable', 'string', Rule::in(['overdue', 'today', 'this_week', 'no_due'])],
        ];
    }
}
