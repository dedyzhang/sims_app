<?php

namespace App\Exports\Cetak;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Setting;
use App\Models\Siswa;
use App\Support\CetakExcelStyle;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Carbon;

class AbsensiSiswaExport implements FromArray, WithTitle, WithEvents
{
    private $siswas;
    private $rekap;
    private $batas;

    public function __construct(private string $idKelas, private string $dari, private string $sampai)
    {
        $this->siswas = Siswa::where('id_kelas', $idKelas)->orderBy('nama')->get();
        $absen = Absensi::where('id_kelas', $idKelas)
            ->whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai)
            ->get()->groupBy('id_siswa');
            
        $this->batas = Setting::get('waktu_terlambat', '07:30');

        $this->rekap = $this->siswas->mapWithKeys(function ($s) use ($absen) {
            $rows = $absen->get($s->uuid, collect());
            $hadir = $rows->where('status', 'hadir');
            return [$s->uuid => [
                'hadir'     => $hadir->count(),
                'terlambat' => $hadir->filter(fn($r) => $r->terlambat($this->batas))->count(),
                'izin'      => $rows->where('status', 'izin')->count(),
                'sakit'     => $rows->where('status', 'sakit')->count(),
                'alpa'      => $rows->where('status', 'alpa')->count(),
            ]];
        });
    }

    public function array(): array
    {
        $rows = [
            ['No', 'NIS', 'Nama Siswa', 'Hadir', 'Tepat Waktu', 'Terlambat', 'Sakit', 'Izin', 'Alpa']
        ];

        foreach ($this->siswas as $i => $s) {
            $r = $this->rekap[$s->uuid];
            $rows[] = [
                $i + 1,
                $s->nis,
                $s->nama,
                $r['hadir'],
                max(0, $r['hadir'] - $r['terlambat']),
                $r['terlambat'],
                $r['sakit'],
                $r['izin'],
                $r['alpa']
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Rekap Absensi Siswa';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $k = Kelas::find($this->idKelas);
                $kelasStr = $k ? "{$k->tingkat}{$k->kelas}" : '-';
                $tglDari = Carbon::parse($this->dari)->isoFormat('D MMM Y');
                $tglSampai = Carbon::parse($this->sampai)->isoFormat('D MMM Y');
                $judul = "REKAP ABSENSI SISWA — KELAS {$kelasStr}\nPERIODE: {$tglDari} - {$tglSampai}";
                
                CetakExcelStyle::kopDanTabel($event->sheet->getDelegate(), $judul, 9, $this->siswas->count());
            },
        ];
    }
}
