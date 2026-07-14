<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGameQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:200'],
            'instructions'     => ['nullable', 'string'],
            'scoring_mode'     => ['required', Rule::in(['accuracy', 'competitive'])],
            'template'         => ['nullable', Rule::in(['quiz', 'match', 'flashcard', 'crossword', 'unjumble'])],
            'max_score'        => ['required', 'integer', 'min:1', 'max:1000'],
            'hide_scores'      => ['sometimes', 'boolean'],
            'show_leaderboard' => ['sometimes', 'boolean'],
            'instant_feedback' => ['sometimes', 'boolean'],
            'opens_at'         => ['nullable', 'date'],
            'due_at'           => ['nullable', 'date', 'after_or_equal:opens_at'],
            'publish_now'      => ['sometimes', 'boolean'],
            'assign_self'      => ['sometimes', 'boolean'],

            'questions'                         => ['required', 'array', 'min:1'],
            'questions.*.type'                  => ['required', Rule::in(['mcq', 'true_false', 'short_answer', 'match'])],
            'questions.*.question_text'         => ['required', 'string', 'max:5000'],
            'questions.*.points'                => ['nullable', 'integer', 'min:1', 'max:100'],
            'questions.*.explanation'           => ['nullable', 'string', 'max:5000'],
            'questions.*.options'               => ['nullable', 'array'],
            'questions.*.options.*.option_text' => ['nullable', 'string', 'max:2000'],
            'questions.*.options.*.is_correct'  => ['sometimes', 'boolean'],
            'questions.*.meta'                  => ['nullable', 'array'],
            'questions.*.meta.answers'          => ['nullable', 'array'],
            'questions.*.meta.answers.*'        => ['nullable', 'string', 'max:500'],
            'questions.*.meta.pairs'            => ['nullable', 'array'],
            'questions.*.meta.pairs.*.left'     => ['nullable', 'string', 'max:500'],
            'questions.*.meta.pairs.*.right'    => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('questions', []) as $i => $q) {
                $type = $q['type'] ?? 'mcq';
                $options = $q['options'] ?? [];

                if (in_array($type, ['mcq', 'true_false'], true)) {
                    $correct = collect($options)->filter(fn ($o) => !empty($o['is_correct']))->count();
                    if ($type === 'mcq' && count($options) < 2) {
                        $validator->errors()->add("questions.$i.options", 'Pilihan ganda minimal 2 opsi.');
                    }
                    if ($type === 'true_false' && count($options) !== 2) {
                        $validator->errors()->add("questions.$i.options", 'Benar/Salah harus tepat 2 opsi.');
                    }
                    if ($correct !== 1) {
                        $validator->errors()->add("questions.$i.options", 'Setiap soal MCQ/TF harus punya tepat 1 jawaban benar.');
                    }
                }

                if ($type === 'short_answer') {
                    $answers = array_values(array_filter($q['meta']['answers'] ?? [], fn ($a) => trim((string) $a) !== ''));
                    if (count($answers) < 1) {
                        $validator->errors()->add("questions.$i.meta.answers", 'Isian singkat minimal 1 kunci jawaban.');
                    }
                }

                if ($type === 'match') {
                    $pairs = $q['meta']['pairs'] ?? [];
                    $valid = collect($pairs)->filter(fn ($p) => trim((string) ($p['left'] ?? '')) !== '' && trim((string) ($p['right'] ?? '')) !== '');
                    if ($valid->count() < 2) {
                        $validator->errors()->add("questions.$i.meta.pairs", 'Pasangkan minimal 2 pasangan.');
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'title.required'     => 'Judul kuis wajib diisi.',
            'questions.required' => 'Minimal satu soal.',
            'questions.min'      => 'Minimal satu soal.',
        ];
    }
}
