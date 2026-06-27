<?php

namespace App\Sarpras\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JadwalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.jadwal.kelola') ?? false;
    }

    public function rules(): array
    {
        return [
            'aset_id' => ['nullable', 'uuid', 'exists:sarpras_aset,id'],
            'nama' => ['required', 'string', 'max:150'],
            'interval_hari' => ['required', 'integer', 'min:1', 'max:3650'],
            'tgl_berikutnya' => ['required', 'date'],
            'aktif' => ['nullable', 'boolean'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama jadwal wajib diisi.',
            'interval_hari.min' => 'Interval minimal 1 hari.',
        ];
    }
}
