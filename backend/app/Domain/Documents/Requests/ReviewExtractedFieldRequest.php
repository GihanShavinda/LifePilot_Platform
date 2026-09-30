<?php

namespace App\Domain\Documents\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewExtractedFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => [
                'required',
                Rule::in(['accept', 'reject', 'edit']),
            ],
            'value' => [
                Rule::requiredIf(fn () => $this->input('decision') === 'edit'),
                'nullable',
            ],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
