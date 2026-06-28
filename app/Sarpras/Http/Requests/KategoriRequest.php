<?php

namespace App\Sarpras\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KategoriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.pengaturan.kelola') ?? false;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'kode' => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', 'uuid', 'exists:sarpras_kategori_aset,id'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['nama.required' => 'Nama kategori wajib diisi.'];
    }
}
