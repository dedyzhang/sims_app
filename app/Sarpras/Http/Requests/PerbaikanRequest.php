<?php

namespace App\Sarpras\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PerbaikanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.perbaikan.kelola') ?? false;
    }

    public function rules(): array
    {
        return [
            'aset_id' => ['nullable', 'uuid', 'exists:sarpras_aset,id'],
            'laporan_id' => ['nullable', 'uuid', 'exists:sarpras_laporan_kerusakan,id'],
            'teknisi_id' => ['nullable', 'uuid', 'exists:sarpras_teknisi,id'],
            'deskripsi' => ['required', 'string', 'max:2000'],
            'status' => ['required', 'in:antri,dikerjakan,selesai,batal'],
            // UANG: integer rupiah.
            'biaya' => ['required', 'integer', 'min:0'],
            'tgl_mulai' => ['nullable', 'date'],
            'tgl_selesai' => ['nullable', 'date', 'after_or_equal:tgl_mulai'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'deskripsi.required' => 'Deskripsi perbaikan wajib diisi.',
            'biaya.integer' => 'Biaya harus angka bulat (rupiah).',
        ];
    }
}
