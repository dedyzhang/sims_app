<?php

namespace App\Http\Controllers;

use App\Exports\Cetak\AbsensiGuruExport;
use App\Exports\Cetak\AgendaExport;
use App\Exports\Cetak\BukuBatasExport;
use App\Exports\Cetak\FormatifExport;
use App\Exports\Cetak\GuruExport;
use App\Exports\Cetak\KelasExport;
use App\Exports\Cetak\PasExport;
use App\Exports\Cetak\PenjabaranExport;
use App\Exports\Cetak\RaporExport;
use App\Exports\Cetak\SiswaExport;
use App\Exports\Cetak\SumatifExport;
use App\Models\Guru;
use App\Models\Kelas;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Menu "Cetak Data" (admin) — export Excel utk siswa/guru/kelas/absensi guru/
 * agenda/nilai, replikasi menu "Cetak Data" di aplikasi lama (smp_ver5),
 * disesuaikan dgn sistem nilai Kurikulum Merdeka smp_v6 (formatif/sumatif/
 * PAS/rapor/penjabaran, bukan harian/olahan/pts/proyek ala app lama).
 */
class CetakController extends Controller
{
    private function kelasList()
    {
        return Kelas::orderBy('tingkat')->orderBy('kelas')->get();
    }

    // ====== Data Siswa ======
    public function siswa()
    {
        return view('cetak.siswa.index', ['kelas' => $this->kelasList()]);
    }

    public function cetakSiswa(string $params)
    {
        $nama = $params === 'semua' ? 'Data Siswa Semua Kelas.xlsx' : $this->namaFileKelas('Data Siswa Kelas', $params);
        return Excel::download(new SiswaExport($params), $nama);
    }

    // ====== Data Guru ======
    public function guru()
    {
        return view('cetak.guru.index');
    }

    public function cetakGuru()
    {
        return Excel::download(new GuruExport, 'Data Guru.xlsx');
    }

    // ====== Data Kelas ======
    public function kelas()
    {
        return view('cetak.kelas.index');
    }

    public function cetakKelas()
    {
        return Excel::download(new KelasExport, 'Data Kelas.xlsx');
    }

    // ====== Absensi Guru ======
    public function absensiGuru()
    {
        return view('cetak.absensi-guru.index');
    }

    public function cetakAbsensiGuru(Request $request)
    {
        $data = $request->validate(['dari' => 'required|date', 'sampai' => 'required|date|after_or_equal:dari']);
        return Excel::download(new AbsensiGuruExport($data['dari'], $data['sampai']), 'Absensi Guru.xlsx');
    }

    // ====== Absensi Siswa ======
    public function absensiSiswa()
    {
        return view('cetak.absensi-siswa.index', ['kelas' => $this->kelasList()]);
    }

    public function cetakAbsensiSiswa(Request $request)
    {
        $data = $request->validate([
            'kelas' => 'required|exists:kelas,uuid',
            'dari' => 'required|date',
            'sampai' => 'required|date|after_or_equal:dari'
        ]);
        
        $k = Kelas::findOrFail($data['kelas']);
        return Excel::download(
            new \App\Exports\Cetak\AbsensiSiswaExport($data['kelas'], $data['dari'], $data['sampai']), 
            "Rekap Absensi Siswa Kelas {$k->tingkat}{$k->kelas}.xlsx"
        );
    }

    // ====== Data Agenda ======
    public function agenda()
    {
        return view('cetak.agenda.index', ['guruList' => Guru::orderBy('nama')->get()]);
    }

    public function cetakAgenda(Request $request)
    {
        $data = $request->validate([
            'dari' => 'required|date', 'sampai' => 'required|date|after_or_equal:dari',
            'id_guru' => 'nullable|exists:gurus,uuid',
        ]);
        return Excel::download(new AgendaExport($data['dari'], $data['sampai'], $data['id_guru'] ?? null), 'Data Agenda.xlsx');
    }

    // ====== Buku Batas ======
    public function bukuBatas()
    {
        return view('cetak.bukuBatas.index', [
            'kelas' => $this->kelasList(),
            'dari'  => now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString(),
            'sampai' => now()->startOfWeek(\Carbon\Carbon::MONDAY)->addDays(5)->toDateString(),
        ]);
    }

    public function cetakBukuBatas(Request $request)
    {
        $data = $request->validate([
            'kelas' => 'required|exists:kelas,uuid',
            'dari'   => 'required|date',
            'sampai' => 'required|date|after_or_equal:dari',
        ]);
        $k = Kelas::findOrFail($data['kelas']);
        return Excel::download(
            new BukuBatasExport($data['kelas'], $data['dari'], $data['sampai']),
            "Buku Batas Kelas {$k->tingkat}{$k->kelas}.xlsx"
        );
    }

    // ====== Nilai Formatif ======
    public function formatif()
    {
        return view('cetak.formatif.index', ['kelas' => $this->kelasList()]);
    }

    public function cetakFormatif(string $params)
    {
        return Excel::download(new FormatifExport($params), $this->namaFileKelas('Nilai Formatif Kelas', $params));
    }

    // ====== Nilai Sumatif ======
    public function sumatif()
    {
        return view('cetak.sumatif.index', ['kelas' => $this->kelasList()]);
    }

    public function cetakSumatif(string $params)
    {
        return Excel::download(new SumatifExport($params), $this->namaFileKelas('Nilai Sumatif Kelas', $params));
    }

    // ====== Nilai Rapor ======
    public function rapor()
    {
        return view('cetak.nilaiRapor.index', ['kelas' => $this->kelasList()]);
    }

    public function cetakRapor(string $params)
    {
        return Excel::download(new RaporExport($params), $this->namaFileKelas('Nilai Rapor Kelas', $params));
    }

    // ====== Nilai PAS ======
    public function pas()
    {
        return view('cetak.pas.index', ['kelas' => $this->kelasList()]);
    }

    public function cetakPas(string $params)
    {
        return Excel::download(new PasExport($params), $this->namaFileKelas('Nilai PAS Kelas', $params));
    }

    // ====== Nilai Penjabaran ======
    public function penjabaran()
    {
        return view('cetak.penjabaran.index', ['kelas' => $this->kelasList()]);
    }

    public function cetakPenjabaran(string $params)
    {
        return Excel::download(new PenjabaranExport($params), $this->namaFileKelas('Nilai Penjabaran Kelas', $params));
    }

    private function namaFileKelas(string $prefix, string $idKelas): string
    {
        $k = Kelas::findOrFail($idKelas);
        return "{$prefix} {$k->tingkat}{$k->kelas}.xlsx";
    }
}
