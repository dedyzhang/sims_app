<?php

namespace App\Sarpras\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MutasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.mutasi.kelola') ?? false;
    }

    public function rules(): array
    {
        return [
            'aset_id' => ['required', 'uuid', 'exists:sarpras_aset,id'],
            'ruangan_tujuan_id' => ['required', 'uuid', 'exists:sarpras_denah_ruangan,id', 'different:ruangan_asal_id'],
            'ruangan_asal_id' => ['nullable', 'uuid', 'exists:sarpras_denah_ruangan,id'],
            'alasan' => ['nullable', 'string', 'max:1000'],
            'tgl_mutasi' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'aset_id.required' => 'Pilih aset yang dimutasi.',
            'ruangan_tujuan_id.required' => 'Pilih ruangan tujuan.',
            'ruangan_tujuan_id.different' => 'Ruangan tujuan harus berbeda dari asal.',
        ];
    }
}
