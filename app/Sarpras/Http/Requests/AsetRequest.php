<?php

namespace App\Sarpras\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.aset.kelola') ?? false;
    }

    public function rules(): array
    {
        $asetId = $this->route('aset')?->id;
        $schoolId = $this->user()?->school_id;

        return [
            'kode' => [
                'required', 'string', 'max:100',
                // Unik per sekolah.
                Rule::unique('sarpras_aset', 'kode')
                    ->where('school_id', $schoolId)
                    ->ignore($asetId),
            ],
            'nama' => ['required', 'string', 'max:200'],
            'kategori_id' => ['nullable', 'uuid', 'exists:sarpras_kategori_aset,id'],
            'ruangan_id' => ['nullable', 'uuid', 'exists:sarpras_denah_ruangan,id'],
            'merk' => ['nullable', 'string', 'max:150'],
            'kondisi' => ['required', 'in:baik,rusak_ringan,rusak_berat,hilang'],
            'status' => ['required', 'in:aktif,dipinjam,perbaikan,dihapus,dimutasi'],
            'tgl_perolehan' => ['nullable', 'date'],
            // UANG: integer rupiah >= 0 (tanpa desimal/float).
            'nilai_perolehan' => ['required', 'integer', 'min:0'],
            'sumber_dana' => ['nullable', 'string', 'max:100'],
            // Spesifikasi key-value (array dari form).
            'spek_key' => ['nullable', 'array'],
            'spek_key.*' => ['nullable', 'string', 'max:100'],
            'spek_val' => ['nullable', 'array'],
            'spek_val.*' => ['nullable', 'string', 'max:255'],
            'foto' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'kode.required' => 'Kode aset wajib diisi.',
            'kode.unique' => 'Kode aset sudah dipakai.',
            'nama.required' => 'Nama aset wajib diisi.',
            'nilai_perolehan.integer' => 'Nilai perolehan harus angka bulat (rupiah, tanpa titik/koma).',
        ];
    }

    /** Susun spesifikasi JSON dari pasangan key-value. */
    public function spesifikasi(): array
    {
        $keys = $this->input('spek_key', []);
        $vals = $this->input('spek_val', []);
        $out = [];
        foreach ($keys as $i => $k) {
            $k = trim((string) $k);
            if ($k !== '') {
                $out[$k] = (string) ($vals[$i] ?? '');
            }
        }

        return $out;
    }
}
