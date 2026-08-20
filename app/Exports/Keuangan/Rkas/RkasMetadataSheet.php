<?php

namespace App\Exports\Keuangan\Rkas;

use App\Exports\Keuangan\Rkas\Concerns\EscapesSpreadsheetText;
use App\Models\RkasPlan;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class RkasMetadataSheet implements FromArray, WithTitle
{
    use EscapesSpreadsheetText;

    public function __construct(private RkasPlan $plan) {}

    public function title(): string
    {
        return 'Metadata';
    }

    public function array(): array
    {
        return [
            ['SIMS ARKAS Companion'],
            ['Nama sekolah', $this->safeText($this->plan->nama_sekolah ?: '-')],
            ['NPSN', $this->safeText($this->plan->npsn ?: '-')],
            ['Tahun anggaran', $this->plan->tahun_anggaran],
            ['Jenjang', $this->safeText($this->plan->jenjang)],
            ['Sumber dana', $this->safeText($this->plan->sumber_dana)],
            ['Versi referensi', $this->safeText($this->plan->referenceSet?->versi ?: '-')],
            ['Label referensi', $this->safeText($this->plan->referenceSet?->label ?: '-')],
            ['Checksum referensi', $this->safeText($this->plan->referenceSet?->source_checksum ?: '-')],
            ['Sumber referensi', $this->safeText($this->plan->referenceSet?->source_url ?: '-')],
            ['Referensi diimpor', optional($this->plan->referenceSet?->created_at)->format('Y-m-d H:i:s') ?: '-'],
            ['Status SIMS', $this->safeText($this->plan->status)],
            ['Catatan integrasi', 'Berkas ini adalah alat bantu penyusunan dan pemeriksaan internal SIMS. Pengesahan, penatausahaan, dan sinkronisasi resmi dilakukan melalui aplikasi ARKAS/MARKAS.'],
        ];
    }
}
