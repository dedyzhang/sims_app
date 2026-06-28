<?php

namespace App\Sarpras\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PengadaanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.pengadaan.ajukan') ?? false;
    }

    public function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'max:200'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'item' => ['required', 'array', 'min:1'],
            'item.*.nama_barang' => ['required', 'string', 'max:200'],
            'item.*.qty' => ['required', 'integer', 'min:1'],
            'item.*.satuan' => ['nullable', 'string', 'max:30'],
            // UANG: integer rupiah per unit.
            'item.*.estimasi_harga' => ['required', 'integer', 'min:0'],
            'item.*.kategori_id' => ['nullable', 'uuid', 'exists:sarpras_kategori_aset,id'],
            'item.*.supplier_id' => ['nullable', 'uuid', 'exists:sarpras_supplier,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'judul.required' => 'Judul pengajuan wajib diisi.',
            'item.required' => 'Minimal satu item barang.',
            'item.*.nama_barang.required' => 'Nama barang wajib diisi.',
            'item.*.qty.min' => 'Qty minimal 1.',
            'item.*.estimasi_harga.integer' => 'Estimasi harga harus angka bulat (rupiah).',
        ];
    }
}
