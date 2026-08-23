<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckQuestionQualityBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $classroom = $this->route('classroom');

        if ($classroom) {
            return $this->user()?->can('manage', $classroom) === true;
        }

        return in_array((string) $this->user()?->access, [
            'guru', 'walikelas', 'kepala', 'kurikulum', 'kesiswaan', 'sarpras', 'admin',
        ], true);
    }

    protected function prepareForValidation(): void
    {
        $questions = collect($this->input('questions', []))->map(function ($question) {
            if (is_array($question) && is_string($question['options'] ?? null)) {
                $question['options'] = array_values(array_filter(array_map(
                    'trim',
                    preg_split('/\r\n|\r|\n/', $question['options']) ?: [],
                ), fn (string $option) => $option !== ''));
            }

            return $question;
        })->all();

        $this->merge(['questions' => $questions]);
    }

    public function rules(): array
    {
        return [
            'questions' => ['required', 'array', 'min:1', 'max:100'],
            'questions.*.grade_level' => ['required', 'string', 'max:80'],
            'questions.*.subject' => ['required', 'string', 'max:120'],
            'questions.*.learning_objective' => ['required', 'string', 'max:1500'],
            'questions.*.question_type' => ['required', Rule::in([
                'mcq', 'mcq_complex', 'true_false', 'short_answer', 'essay', 'match',
            ])],
            'questions.*.question_text' => ['required', 'string', 'min:8', 'max:5000'],
            'questions.*.options' => ['nullable', 'array', 'max:10'],
            'questions.*.options.*' => ['required', 'string', 'max:1000'],
            'questions.*.answer_key' => ['nullable', 'string', 'max:1500'],
        ];
    }

    public function messages(): array
    {
        return [
            'questions.required' => 'Minimal satu soal wajib dikirim untuk diperiksa.',
            'questions.max' => 'Maksimal 100 soal dapat diperiksa sekaligus.',
            'questions.*.grade_level.required' => 'Kelas atau jenjang wajib diisi.',
            'questions.*.subject.required' => 'Mata pelajaran wajib diisi.',
            'questions.*.learning_objective.required' => 'Materi atau tujuan pembelajaran wajib diisi.',
            'questions.*.question_type.in' => 'Ada tipe soal yang tidak didukung.',
            'questions.*.question_text.required' => 'Teks soal wajib diisi.',
            'questions.*.question_text.min' => 'Teks soal minimal 8 karakter.',
            'questions.*.options.max' => 'Opsi jawaban maksimal 10 pilihan per soal.',
        ];
    }
}
