<?php

namespace App\Sarpras\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PeminjamanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.peminjaman.ajukan') ?? false;
    }

    public function rules(): array
    {
        return [
            'keperluan' => ['required', 'string', 'max:1000'],
            // Periode tunggal untuk seluruh pengajuan (ruangan & aset).
            'mulai' => ['required', 'date'],
            'selesai' => ['required', 'date', 'after:mulai'],
            // Ruangan opsional.
            'ruangan_id' => ['nullable', 'uuid', 'exists:sarpras_denah_ruangan,id'],
            // Aset opsional (bisa beberapa).
            'aset_id' => ['nullable', 'array'],
            'aset_id.*' => ['uuid', 'exists:sarpras_aset,id'],
            'qty' => ['nullable', 'array'],
            'qty.*' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ruangan_id' => $this->ruangan_id ?: null,
            'aset_id' => array_values(array_filter((array) $this->input('aset_id', []))),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (! $this->ruangan_id && empty($this->aset_id)) {
                $v->errors()->add('ruangan_id', 'Pilih ruangan dan/atau minimal satu aset.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'keperluan.required' => 'Keperluan wajib diisi.',
            'mulai.required' => 'Waktu mulai wajib diisi.',
            'selesai.required' => 'Waktu selesai wajib diisi.',
            'selesai.after' => 'Waktu selesai harus setelah waktu mulai.',
        ];
    }
}
