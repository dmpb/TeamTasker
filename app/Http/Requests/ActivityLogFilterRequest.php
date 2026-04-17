<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivityLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'event' => $this->filled('event') ? trim((string) $this->input('event')) : null,
            'actor_id' => $this->filled('actor_id') ? (int) $this->input('actor_id') : null,
            'date_from' => $this->filled('date_from') ? trim((string) $this->input('date_from')) : null,
            'date_to' => $this->filled('date_to') ? trim((string) $this->input('date_to')) : null,
            'q' => $this->filled('q') ? trim((string) $this->input('q')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event' => ['sometimes', 'nullable', 'string', 'max:100'],
            'actor_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
