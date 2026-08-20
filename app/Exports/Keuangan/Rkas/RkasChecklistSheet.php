<?php

namespace App\Exports\Keuangan\Rkas;

use App\Models\RkasPlan;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class RkasChecklistSheet implements FromArray, WithTitle
{
    public function __construct(private RkasPlan $plan) {}

    public function title(): string
    {
        return 'Checklist ARKAS';
    }

    public function array(): array
    {
        return [
            ['Checklist proses', 'Status / catatan'],
            ['1. Jalankan validasi di SIMS dan selesaikan error', $this->plan->validations->where('severity', 'error')->isEmpty() ? 'Lulus' : 'Belum lulus'],
            ['2. Unduh dan review Excel/PDF bersama kepala sekolah/komite', 'Belum dicatat'],
            ['3. Input atau cocokkan Kertas Kerja pada aplikasi ARKAS desktop', 'Dilakukan manual di ARKAS'],
            ['4. Lakukan pengesahan sesuai alur sekolah', 'Dilakukan manual di ARKAS/MARKAS'],
            ['5. Sinkronkan melalui ARKAS/MARKAS jika diwajibkan', 'Dilakukan manual di ARKAS/MARKAS'],
            ['6. Kembali ke SIMS dan catat status serta bukti dokumen', 'Catat melalui halaman detail RKAS'],
            ['Catatan integrasi', 'SIMS tidak mengunggah ke ARKAS/MARKAS dan tidak membaca database lokal ARKAS.'],
        ];
    }
}
