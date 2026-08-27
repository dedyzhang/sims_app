<?php

namespace App\Exports\Cetak;

use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SiswaExport implements WithMultipleSheets
{
    use Exportable;

    /** @param string $idKelas 'semua', 'tingkat-{N}', atau uuid Kelas */
    public function __construct(private string $idKelas)
    {
    }

    public function sheets(): array
    {
        return [
            new SiswaDataSheet($this->idKelas),
            new SiswaStatistikSheet($this->idKelas),
        ];
    }

    /**
     * Interpretasi param filter siswa: 'semua' (seluruh siswa), 'tingkat-{N}' (satu tingkat/angkatan,
     * gabungan semua kelas di tingkat itu), atau uuid Kelas (satu kelas). Dipakai bersama oleh
     * SiswaDataSheet & SiswaStatistikSheet supaya filter selalu konsisten antar sheet.
     */
    public static function query(string $params): Builder
    {
        $q = Siswa::with('kelas')->orderBy('nama');

        if ($params === 'semua') {
            return $q;
        }
        if (str_starts_with($params, 'tingkat-')) {
            $tingkat = (int) substr($params, 8);

            return $q->whereHas('kelas', fn ($k) => $k->where('tingkat', $tingkat));
        }

        return $q->where('id_kelas', $params);
    }

    /** Label deskriptif filter, dipakai di kop sheet Statistik & (via CetakController) nama file. */
    public static function labelFilter(string $params): string
    {
        if ($params === 'semua') {
            return 'Semua Kelas';
        }
        if (str_starts_with($params, 'tingkat-')) {
            return 'Tingkat ' . substr($params, 8);
        }
        $k = Kelas::find($params);

        return $k ? "Kelas {$k->tingkat}{$k->kelas}" : '-';
    }
}
