<?php

namespace App\Sarpras\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/*
| Validasi penyimpanan koordinat hotspot dari editor (klik pada denah).
| Koordinat WAJIB persen 0-100 supaya responsif & tidak bergeser.
*/
class RuanganPosisiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.denah.kelola') ?? false;
    }

    public function rules(): array
    {
        return [
            'pos_x' => ['required', 'numeric', 'between:0,100'],
            'pos_y' => ['required', 'numeric', 'between:0,100'],
            'lebar' => ['nullable', 'numeric', 'between:1,100'],
            'tinggi' => ['nullable', 'numeric', 'between:1,100'],
            'warna' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'pos_x.between' => 'Koordinat X harus 0–100 persen.',
            'pos_y.between' => 'Koordinat Y harus 0–100 persen.',
            'warna.regex' => 'Warna harus format hex (mis. #059669).',
        ];
    }
}
