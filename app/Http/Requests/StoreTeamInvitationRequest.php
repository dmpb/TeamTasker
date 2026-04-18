<?php

namespace App\Http\Requests;

use App\Enums\TeamMemberRole;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamInvitationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim((string) $this->input('email'))),
            ]);
        }
    }

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
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $normalized = strtolower(trim((string) $value));

                    if ($normalized === '') {
                        return;
                    }

                    if (! User::query()->whereRaw('lower(email) = ?', [$normalized])->exists()) {
                        $fail(__('No existe una cuenta registrada con este correo.'));
                    }
                },
            ],
            'role' => [
                'required',
                'string',
                Rule::in([TeamMemberRole::Admin->value, TeamMemberRole::Member->value]),
            ],
        ];
    }
}
