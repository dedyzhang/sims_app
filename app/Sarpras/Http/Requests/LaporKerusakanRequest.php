<?php

namespace App\Sarpras\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LaporKerusakanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.kerusakan.lapor') ?? false;
    }

    public function rules(): array
    {
        return [
            'aset_id' => ['nullable', 'uuid', 'exists:sarpras_aset,id'],
            'ruangan_id' => ['nullable', 'uuid', 'exists:sarpras_denah_ruangan,id'],
            'deskripsi' => ['required', 'string', 'min:5', 'max:2000'],
            'urgensi' => ['required', 'in:rendah,sedang,tinggi,darurat'],
            // 1-4 foto. Mentah max 10MB (longgar) — nanti dikompres <=2MB.
            'foto' => ['nullable', 'array', 'max:4'],
            'foto.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'deskripsi.required' => 'Deskripsi kerusakan wajib diisi.',
            'urgensi.in' => 'Tingkat urgensi tidak valid.',
            'foto.max' => 'Maksimal 4 foto.',
            'foto.*.image' => 'Berkas harus berupa gambar (jpg, jpeg, png, webp).',
            'foto.*.mimes' => 'Format foto harus jpg, jpeg, png, atau webp.',
            'foto.*.max' => 'Ukuran tiap foto maksimal 10MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Aset dan ruangan benar-benar opsional; deskripsi kerusakan tetap wajib.
        $this->merge([
            'aset_id' => $this->aset_id ?: null,
            'ruangan_id' => $this->ruangan_id ?: null,
        ]);
    }
}
