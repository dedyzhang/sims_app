<?php

namespace App\Sarpras\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DenahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.denah.kelola') ?? false;
    }

    public function rules(): array
    {
        return [
            // Nama opsional: bila kosong dibuat otomatis dari gedung + lantai.
            'nama' => ['nullable', 'string', 'max:150'],
            'gedung' => ['nullable', 'string', 'max:100'],
            'lantai' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            // Gambar denah mentah max 10MB -> dikompres <=2MB.
            'gambar' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // Minimal salah satu pengenal lantai harus ada agar bisa dibuatkan nama.
            if (! filled($this->nama) && ! filled($this->lantai) && ! filled($this->gedung)) {
                $v->errors()->add('lantai', 'Isi minimal Lantai atau Nama denah.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'gambar.max' => 'Ukuran gambar denah maksimal 10MB.',
            'gambar.mimes' => 'Format gambar harus jpg, jpeg, png, atau webp.',
        ];
    }
}
