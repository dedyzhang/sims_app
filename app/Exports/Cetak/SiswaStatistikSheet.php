<?php

namespace App\Exports\Cetak;

use App\Models\Siswa;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class SiswaStatistikSheet implements FromView, WithTitle, WithEvents
{
    public function __construct(private string $idKelas)
    {
    }

    public function view(): View
    {
        $q = Siswa::with('kelas');
        if ($this->idKelas !== 'semua') {
            $q->where('id_kelas', $this->idKelas);
        }
        $siswas = $q->get();

        // 1. Usia vs Gender
        // Compute age for each student
        $ages = [];
        $religions = [];
        $classes = [];
        
        $statsUsia = ['L' => [], 'P' => []];
        $statsAgama = ['L' => [], 'P' => []];
        $statsKelas = ['L' => [], 'P' => []];
        $totalGender = ['L' => 0, 'P' => 0];

        foreach ($siswas as $siswa) {
            $jk = $siswa->jk; // 'L' or 'P'
            $totalGender[$jk]++;

            // Age
            $age = $siswa->tanggal_lahir ? Carbon::parse($siswa->tanggal_lahir)->age : null;
            if ($age !== null) {
                if (!in_array($age, $ages)) $ages[] = $age;
                if (!isset($statsUsia[$jk][$age])) $statsUsia[$jk][$age] = 0;
                $statsUsia[$jk][$age]++;
            }

            // Religion
            $agama = strtolower($siswa->agama ?: 'tidak diketahui');
            if (!in_array($agama, $religions)) $religions[] = $agama;
            if (!isset($statsAgama[$jk][$agama])) $statsAgama[$jk][$agama] = 0;
            $statsAgama[$jk][$agama]++;

            // Class
            $kelasNama = $siswa->kelas ? "{$siswa->kelas->tingkat}{$siswa->kelas->kelas}" : 'Tidak Ada Kelas';
            if (!in_array($kelasNama, $classes)) $classes[] = $kelasNama;
            if (!isset($statsKelas[$jk][$kelasNama])) $statsKelas[$jk][$kelasNama] = 0;
            $statsKelas[$jk][$kelasNama]++;
        }

        sort($ages);
        sort($religions);
        sort($classes);

        // Fill missing keys with 0 for cleaner view logic
        foreach ($ages as $age) {
            if (!isset($statsUsia['L'][$age])) $statsUsia['L'][$age] = 0;
            if (!isset($statsUsia['P'][$age])) $statsUsia['P'][$age] = 0;
        }
        foreach ($religions as $agama) {
            if (!isset($statsAgama['L'][$agama])) $statsAgama['L'][$agama] = 0;
            if (!isset($statsAgama['P'][$agama])) $statsAgama['P'][$agama] = 0;
        }
        foreach ($classes as $kelasNama) {
            if (!isset($statsKelas['L'][$kelasNama])) $statsKelas['L'][$kelasNama] = 0;
            if (!isset($statsKelas['P'][$kelasNama])) $statsKelas['P'][$kelasNama] = 0;
        }

        return view('exports.siswa_statistik', compact(
            'ages', 'religions', 'classes', 
            'statsUsia', 'statsAgama', 'statsKelas', 'totalGender',
            'siswas'
        ));
    }

    public function title(): string
    {
        return 'Statistik';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Formatting will be handled mostly by Blade HTML table.
                // You can add specific column width sizing here if needed.
                $event->sheet->getDelegate()->getColumnDimension('A')->setWidth(15);
                $event->sheet->getDelegate()->getColumnDimension('B')->setWidth(15);
                $event->sheet->getDelegate()->getColumnDimension('C')->setWidth(15);
                $event->sheet->getDelegate()->getColumnDimension('D')->setWidth(15);
                $event->sheet->getDelegate()->getColumnDimension('E')->setWidth(15);
            },
        ];
    }
}
