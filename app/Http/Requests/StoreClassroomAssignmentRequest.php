<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassroomAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('classroom.max_files', 10);
        $maxKb = (int) config('classroom.max_file_mb', 20) * 1024;

        return [
            'title'        => ['required', 'string', 'max:160'],
            'instructions' => ['nullable', 'string', 'max:200000'],
            'kelas'        => ['required', 'array', 'min:1'],
            'kelas.*'      => ['string', 'exists:kelas,uuid'],
            'type'         => ['required', 'in:tugas,latihan,kuis'],
            'max_score'    => ['required', 'integer', 'min:1', 'max:1000'],
            'allow_late'   => ['nullable', 'boolean'],
            'opens_at'     => ['nullable', 'date'],
            'due_at'       => ['nullable', 'date', 'after_or_equal:opens_at'],
            'publish_now'  => ['nullable', 'boolean'],
            'hide_scores'  => ['nullable', 'boolean'],
            'files'        => ['nullable', 'array', "max:{$max}"],
            'files.*'      => ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', "max:{$maxKb}"],
        ];
    }

    public function messages(): array
    {
        return ['due_at.after_or_equal' => 'Batas waktu harus setelah waktu mulai.'];
    }

    public function attributes(): array
    {
        return ['title' => 'judul tugas', 'due_at' => 'batas waktu', 'opens_at' => 'waktu mulai'];
    }
}
