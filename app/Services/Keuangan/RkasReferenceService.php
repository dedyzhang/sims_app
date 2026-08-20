<?php

namespace App\Services\Keuangan;

use App\Models\RkasReferenceSet;
use Illuminate\Support\Collection;

class RkasReferenceService
{
    public function parseRules(?string $json): array
    {
        if (trim((string) $json) === '') {
            return [];
        }
        $rules = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($rules)) {
            throw new \InvalidArgumentException('Format aturan harus berupa JSON object.');
        }

        return $rules;
    }

    public function rows(Collection $rows): Collection
    {
        $header = $rows->first()?->map(fn ($value) => mb_strtolower(trim((string) $value)))->all() ?? [];
        $aliases = [
            'kode kegiatan' => 'kode_kegiatan', 'kode_kegiatan' => 'kode_kegiatan', 'kode' => 'kode_kegiatan',
            'snp' => 'snp', 'komponen' => 'komponen', 'uraian kegiatan' => 'uraian_kegiatan',
            'uraian_kegiatan' => 'uraian_kegiatan', 'kode rekening belanja' => 'kode_rekening_belanja',
            'kode_rekening_belanja' => 'kode_rekening_belanja',
        ];
        $mappedHeader = array_map(fn ($value) => $aliases[$value] ?? $value, $header);

        if (! in_array('kode_kegiatan', $mappedHeader, true) || ! in_array('uraian_kegiatan', $mappedHeader, true)) {
            throw new \InvalidArgumentException('Berkas referensi wajib memiliki kolom kode_kegiatan dan uraian_kegiatan.');
        }

        return $rows->skip(1)->map(function ($row) use ($mappedHeader) {
            $values = $row->all();
            $data = [];
            foreach ($mappedHeader as $index => $key) {
                if ($key !== '') {
                    $data[$key] = trim((string) ($values[$index] ?? ''));
                }
            }

            return $data;
        })->filter(fn (array $row) => $row['kode_kegiatan'] !== '' && $row['uraian_kegiatan'] !== '')->values();
    }

    public function deactivatePeers(RkasReferenceSet $set): void
    {
        RkasReferenceSet::query()
            ->where('tahun_anggaran', $set->tahun_anggaran)
            ->where('jenjang', $set->jenjang)
            ->where('sumber_dana', $set->sumber_dana)
            ->where('uuid', '!=', $set->uuid)
            ->update(['is_active' => false]);
    }
}
