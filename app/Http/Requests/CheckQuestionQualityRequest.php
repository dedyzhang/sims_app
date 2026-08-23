<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckQuestionQualityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $classroom = $this->route('classroom');

        return $this->user()?->can('manage', $classroom) === true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('options'))) {
            $this->merge([
                'options' => array_values(array_filter(array_map(
                    'trim',
                    preg_split('/\r\n|\r|\n/', (string) $this->input('options')) ?: [],
                ), fn (string $option) => $option !== '')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'grade_level' => ['required', 'string', 'max:80'],
            'subject' => ['required', 'string', 'max:120'],
            'learning_objective' => ['required', 'string', 'max:1500'],
            'question_type' => ['required', Rule::in([
                'mcq', 'mcq_complex', 'true_false', 'short_answer', 'essay', 'match',
            ])],
            'question_text' => ['required', 'string', 'min:8', 'max:5000'],
            'options' => ['nullable', 'array', 'max:10'],
            'options.*' => ['required', 'string', 'max:1000'],
            'answer_key' => ['nullable', 'string', 'max:1500'],
        ];
    }

    public function messages(): array
    {
        return [
            'grade_level.required' => 'Kelas atau jenjang wajib diisi.',
            'subject.required' => 'Mata pelajaran wajib diisi.',
            'learning_objective.required' => 'Materi atau tujuan pembelajaran wajib diisi.',
            'question_type.required' => 'Tipe soal wajib dipilih.',
            'question_type.in' => 'Tipe soal tidak didukung.',
            'question_text.required' => 'Teks soal wajib diisi.',
            'question_text.min' => 'Teks soal minimal 8 karakter.',
            'options.max' => 'Opsi jawaban maksimal 10 pilihan.',
        ];
    }
}
