<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EventKegiatanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kegiatan = \App\Models\EventKegiatan::create([
            'nama_kegiatan' => 'Bimtek Literasi dan Numerasi BOS',
            'tema' => 'Menguatkan Budaya Literasi dan Numerasi Untuk Mewujudkan Sekolah Yang Berkualitas',
            'tanggal_mulai' => '2026-09-26',
            'waktu_mulai' => '07:30',
            'tempat' => 'SMP Swasta Sion Tanjungpinang',
            'form_fields' => [
                ['name' => 'nama', 'label' => 'Nama Lengkap (Gelar)', 'type' => 'text', 'required' => true],
                ['name' => 'instansi', 'label' => 'Instansi', 'type' => 'text', 'required' => true],
            ],
            'status' => 'published',
        ]);

        $pesertas = [
            ['nama' => 'Nur Maladewi, S.Pd., M.Pd.', 'instansi' => 'BPMP Kepri'],
            ['nama' => 'Drs. Wahyu Jatmiko', 'instansi' => 'SMP Swasta Sion'],
            ['nama' => 'Purnama Tampubolon, S.Pd., Gr.', 'instansi' => 'SMP Swasta Sion'],
        ];

        foreach ($pesertas as $p) {
            \App\Models\EventPeserta::create([
                'event_kegiatan_id' => $kegiatan->id,
                'biodata' => $p,
                'qr_token' => strtoupper(\Illuminate\Support\Str::random(8)),
                'status_kehadiran' => 'belum',
            ]);
        }
    }
}
