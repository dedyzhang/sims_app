<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GradeClassroomSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'score'    => ['required', 'integer', 'min:0', 'max:1000'],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
