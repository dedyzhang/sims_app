<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassroomSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('classroom.max_files', 10);
        $maxKb = (int) config('classroom.max_file_mb', 20) * 1024;

        $assignment = $this->route('assignment');
        $hasExistingFiles = false;
        if ($assignment) {
            $existing = \App\Models\ClassroomSubmission::where('assignment_id', $assignment->uuid)
                ->where('student_id', $this->user()->uuid)
                ->first();
            if ($existing && $existing->files()->exists()) {
                $hasExistingFiles = true;
            }
        }

        return [
            'body'    => ['nullable', 'string', 'max:20000', $hasExistingFiles ? '' : 'required_without:files'],
            'files'   => ['nullable', 'array', "max:{$max}", $hasExistingFiles ? '' : 'required_without:body'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', "max:{$maxKb}"],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required_without'  => 'Isi jawaban atau lampirkan minimal satu file.',
            'files.required_without' => 'Lampirkan file atau isi jawaban.',
        ];
    }
}
