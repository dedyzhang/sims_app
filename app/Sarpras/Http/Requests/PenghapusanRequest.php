<?php

namespace App\Sarpras\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PenghapusanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.penghapusan.ajukan') ?? false;
    }

    public function rules(): array
    {
        return [
            'aset_id' => ['required', 'uuid', 'exists:sarpras_aset,id'],
            'alasan' => ['required', 'string', 'max:1000'],
            'metode' => ['required', 'in:jual,musnah,hibah,lainnya'],
        ];
    }

    public function messages(): array
    {
        return [
            'aset_id.required' => 'Pilih aset yang akan dihapus.',
            'alasan.required' => 'Alasan penghapusan wajib diisi.',
        ];
    }
}
