<?php

namespace App\Sarpras\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeknisiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.teknisi.kelola') ?? false;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'tipe' => ['required', 'in:internal,eksternal'],
            'spesialisasi' => ['nullable', 'string', 'max:150'],
            'telepon' => ['nullable', 'string', 'max:40'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return ['nama.required' => 'Nama teknisi wajib diisi.'];
    }
}
