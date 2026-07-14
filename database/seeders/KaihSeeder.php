<?php

namespace Database\Seeders;

use App\Models\KaihOpsi;
use App\Models\KaihPertanyaan;
use Illuminate\Database\Seeder;

/** Default 7 pertanyaan "7 Kebiasaan Anak Indonesia Hebat" — teks & opsi bisa diedit admin/kurikulum setelah seed. */
class KaihSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [1, 'Bangun Pagi', 'Pukul berapa kamu bangun pagi ini?', [
                ['Sebelum pukul 05.00', 4],
                ['05.00 - 05.30', 3],
                ['05.31 - 06.00', 2],
                ['Setelah pukul 06.00', 1],
            ]],
            [2, 'Beribadah', 'Apakah kamu sudah beribadah pagi ini sesuai keyakinanmu?', [
                ['Sudah, dengan khusyuk', 4],
                ['Sudah, tapi terburu-buru', 3],
                ['Belum, tapi akan segera', 2],
                ['Belum / lupa', 1],
            ]],
            [3, 'Berolahraga', 'Apakah kamu berolahraga atau bergerak aktif pagi ini?', [
                ['Ya, lebih dari 15 menit', 4],
                ['Ya, sekitar 5-15 menit', 3],
                ['Hanya peregangan ringan', 2],
                ['Tidak sama sekali', 1],
            ]],
            [4, 'Makan Sehat dan Bergizi', 'Apakah kamu sudah sarapan sehat pagi ini?', [
                ['Ya, sarapan lengkap & bergizi', 4],
                ['Ya, tapi seadanya', 3],
                ['Hanya minum / camilan ringan', 2],
                ['Belum sarapan', 1],
            ]],
            [5, 'Gemar Belajar', 'Apakah kamu belajar atau membaca sesuatu sebelum berangkat sekolah?', [
                ['Ya, membaca buku pelajaran/buku lain', 4],
                ['Ya, sebentar saja', 3],
                ['Hanya mengulang tugas kemarin', 2],
                ['Tidak sempat belajar', 1],
            ]],
            [6, 'Bermasyarakat', 'Apakah kamu berinteraksi baik dengan keluarga/orang di sekitarmu pagi ini?', [
                ['Ya, membantu pekerjaan rumah & menyapa', 4],
                ['Ya, sekadar menyapa', 3],
                ['Hanya sedikit berinteraksi', 2],
                ['Belum sempat berinteraksi', 1],
            ]],
            [7, 'Tidur Cepat', 'Pukul berapa kamu tidur malam tadi?', [
                ['Sebelum pukul 21.00', 4],
                ['21.00 - 21.30', 3],
                ['21.31 - 22.00', 2],
                ['Setelah pukul 22.00', 1],
            ]],
        ];

        foreach ($data as [$urutan, $kebiasaan, $pertanyaan, $opsis]) {
            $p = KaihPertanyaan::firstOrCreate(
                ['urutan' => $urutan],
                ['kebiasaan' => $kebiasaan, 'pertanyaan' => $pertanyaan, 'aktif' => true]
            );
            if ($p->opsi()->exists()) {
                continue;
            }
            foreach ($opsis as $i => [$label, $bobot]) {
                KaihOpsi::create([
                    'id_pertanyaan' => $p->uuid,
                    'label'         => $label,
                    'bobot'         => $bobot,
                    'urutan'        => $i,
                ]);
            }
        }

        $this->command?->info('✓ 7 KAIH: default pertanyaan & opsi ter-seed.');
    }
}
