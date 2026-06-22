<?php

namespace App\Sarpras\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.supplier.kelola') ?? false;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:200'],
            'kontak' => ['nullable', 'string', 'max:150'],
            'telepon' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'npwp' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return ['nama.required' => 'Nama supplier wajib diisi.'];
    }
}
