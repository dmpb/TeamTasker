<?php

namespace App\Http\Requests;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
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
            && $this->user()?->can('create', [Comment::class, $task]) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
        ];
    }
}
