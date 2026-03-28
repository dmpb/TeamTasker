<?php

namespace App\Http\Requests;

use App\Enums\TeamMemberRole;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Team|null $team */
        $team = $this->route('team');

        return $team !== null && $this->user()?->can('manageMembers', $team) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => [
                'required',
                'string',
                Rule::in([TeamMemberRole::Admin->value, TeamMemberRole::Member->value]),
            ],
        ];
    }
}
