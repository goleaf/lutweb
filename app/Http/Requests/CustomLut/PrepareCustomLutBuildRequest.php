<?php

namespace App\Http\Requests\CustomLut;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PrepareCustomLutBuildRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'expected_revision' => ['required', 'integer', 'min:1'],
            'expected_parameters_hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'build_request_id' => ['required', 'uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['expected_revision', 'expected_parameters_hash', 'build_request_id'];
            $unknown = array_diff(array_keys($this->all()), $allowed);

            if ($unknown !== []) {
                $validator->errors()->add('build', 'The build request contains unsupported fields.');
            }
        });
    }
}
