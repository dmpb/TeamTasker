<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTeamProjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('include_archived') && ! $this->filled('archive_scope')) {
            $this->merge([
                'archive_scope' => $this->boolean('include_archived') ? 'all' : 'active',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'include_archived' => ['sometimes'],
            'archive_scope' => ['sometimes', Rule::in(['active', 'all', 'archived'])],
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
