<?php

namespace App\Http\Requests;

use App\Models\Column;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReorderColumnsRequest extends FormRequest
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
            && $this->user()?->can('update', $project) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'column_ids' => ['required', 'array'],
            'column_ids.*' => ['integer'],
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
            $raw = $this->input('column_ids', []);
            $ids = array_values(array_map(static fn (mixed $id): int => (int) $id, is_array($raw) ? $raw : []));

            $expected = Column::query()
                ->where('project_id', $project->id)
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();

            if ($expected === []) {
                if ($ids !== []) {
                    $validator->errors()->add('column_ids', 'There are no columns on this board.');
                }

                return;
            }

            if (count($ids) !== count(array_unique($ids))) {
                $validator->errors()->add('column_ids', 'Duplicate column ids are not allowed.');

                return;
            }

            $sorted = $ids;
            sort($sorted);

            if ($expected !== $sorted) {
                $validator->errors()->add('column_ids', 'The column list must match this project\'s columns exactly.');
            }
        });
    }
}
