<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi di controller (Policy)
    }

    public function rules(): array
    {
        return [
            'title'                => ['required', 'string', 'max:160'],
            'id_pelajaran'         => ['nullable', 'string', 'exists:pelajarans,uuid'],
            'id_semester'          => ['nullable', 'integer', 'exists:semesters,id'],
            'kelas'                => ['required', 'array', 'min:1'],
            'kelas.*'              => ['string', 'exists:kelas,uuid'],
            'description'          => ['nullable', 'string', 'max:5000'],
            'cover_color'          => ['nullable', 'string', 'max:20'],
            'publish_mode'         => ['required', 'in:draft,now,schedule'],
            'scheduled_publish_at' => ['nullable', 'required_if:publish_mode,schedule', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'kelas.required' => 'Pilih minimal satu rombel/kelas.',
            'kelas.min'      => 'Pilih minimal satu rombel/kelas.',
        ];
    }

    public function attributes(): array
    {
        return ['title' => 'judul', 'kelas' => 'rombel', 'scheduled_publish_at' => 'waktu jadwal terbit'];
    }
}
