<?php

namespace App\Http\Requests\CustomLut;

use Illuminate\Foundation\Http\FormRequest;

class SelectWizardStyleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->hasVerifiedEmail()
            && ! $this->user()->is_suspended;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'expected_revision' => ['required', 'integer', 'min:1'],
            'mutation_id' => ['required', 'uuid'],
            'style_id' => ['nullable', 'string'],
        ];
    }
}
