<?php

namespace App\Sarpras\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RuanganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.denah.kelola') ?? false;
    }

    public function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'max:50'],
            'nama' => ['nullable', 'string', 'max:150'],
            // KOORDINAT PERSEN wajib 0-100 (validasi anti out-of-range).
            'pos_x' => ['required', 'numeric', 'between:0,100'],
            'pos_y' => ['required', 'numeric', 'between:0,100'],
            'lebar' => ['nullable', 'numeric', 'between:1,100'],
            'tinggi' => ['nullable', 'numeric', 'between:1,100'],
            'kapasitas' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'gambar_denah' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'foto' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'kode.required' => 'Kode ruangan wajib diisi (mis. 7A).',
            'pos_x.between' => 'Koordinat X harus di antara 0–100 (persen).',
            'pos_y.between' => 'Koordinat Y harus di antara 0–100 (persen).',
        ];
    }
}
