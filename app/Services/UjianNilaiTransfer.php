<?php

namespace App\Services;

use App\Models\Materi;
use App\Models\Ngajar;
use App\Models\NilaiPas;
use App\Models\NilaiPts;
use App\Models\NilaiSumatif;
use App\Models\RaporKonfirmasi;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\UjianAttempt;

/**
 * Transfer otomatis skor UjianAttempt final ke buku nilai yang sudah ada (Nilai
 * PTS/PAS/Sumatif), dipicu dari UjianGrader begitu attempt selesai dinilai penuh —
 * BUKAN aksi manual. Selalu taat kunci RaporKonfirmasi: kalau terkunci, tulis
 * dibatalkan & skor TETAP aman di attempt (status_transfer_nilai='gagal_terkunci'),
 * tak pernah hilang diam-diam.
 */
class UjianNilaiTransfer
{
    public function transfer(UjianAttempt $attempt): void
    {
        $ujianKelas = $attempt->ujianKelas;
        $ujian = $ujianKelas?->ujian;
        if (!$ujianKelas || !$ujian) {
            $attempt->update(['status_transfer_nilai' => 'gagal_lain']);
            return;
        }

        // Nilai* menyimpan uuid Siswa sendiri (bukan uuid User) — id_siswa pada
        // ujian_attempts adalah FK ke users (dipakai utk pengecekan kepemilikan saat
        // pengerjaan), jadi harus dijembatani ke sini.
        $siswa = Siswa::where('id_login', $attempt->id_siswa)->first();
        if (!$siswa) {
            $attempt->update(['status_transfer_nilai' => 'gagal_lain']);
            return;
        }

        $nilai = (int) round((float) $attempt->total_skor);

        if ($ujian->target_nilai === 'sumatif') {
            $this->transferSumatif($attempt, $ujian, $siswa, $nilai);
            return;
        }

        $this->transferPtsPas($attempt, $ujian, $ujianKelas, $siswa, $nilai);
    }

    private function transferPtsPas(UjianAttempt $attempt, $ujian, $ujianKelas, Siswa $siswa, int $nilai): void
    {
        $ngajar = Ngajar::where('id_kelas', $ujianKelas->id_kelas)
            ->where('id_pelajaran', $ujian->id_pelajaran)->first();
        $semester = Semester::aktif();

        if (!$ngajar || !$semester) {
            $attempt->update(['status_transfer_nilai' => 'gagal_lain']);
            return;
        }

        if (RaporKonfirmasi::where('id_ngajar', $ngajar->uuid)->where('id_semester', $semester->id)->exists()) {
            $attempt->update(['status_transfer_nilai' => 'gagal_terkunci']);
            return;
        }

        $model = $ujian->target_nilai === 'pts' ? NilaiPts::class : NilaiPas::class;
        $model::updateOrCreate(
            ['id_ngajar' => $ngajar->uuid, 'id_siswa' => $siswa->uuid, 'id_semester' => $semester->id],
            ['nilai' => $nilai]
        );

        $attempt->update(['status_transfer_nilai' => 'berhasil']);
    }

    private function transferSumatif(UjianAttempt $attempt, $ujian, Siswa $siswa, int $nilai): void
    {
        $materi = Materi::find($ujian->id_materi);
        if (!$materi) {
            $attempt->update(['status_transfer_nilai' => 'gagal_lain']);
            return;
        }

        if (RaporKonfirmasi::where('id_ngajar', $materi->id_ngajar)->where('id_semester', $materi->id_semester)->exists()) {
            $attempt->update(['status_transfer_nilai' => 'gagal_terkunci']);
            return;
        }

        NilaiSumatif::updateOrCreate(
            ['id_materi' => $materi->uuid, 'id_siswa' => $siswa->uuid],
            ['nilai' => $nilai]
        );

        $attempt->update(['status_transfer_nilai' => 'berhasil']);
    }
}
