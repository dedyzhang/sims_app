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
        $ngajar = $this->resolveNgajar($ujian, $ujianKelas);
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

    /**
     * Kalau kelas ini punya guru pengampu tersimpan, resolusi Ngajar diprioritaskan lewat
     * itu — deterministik, tak ambigu walau kelas ini diajar 2+ guru. Baris ujian_kelas
     * lama (dibuat sblm fitur pengampu ada) belum punya nilai ini, jatuh balik ke ->first()
     * spt semula (satu2nya pilihan yg ada saat itu, tetap benar kalau cuma 1 guru cocok).
     */
    private function resolveNgajar($ujian, $ujianKelas): ?Ngajar
    {
        return $ujianKelas->id_guru_pengampu
            ? Ngajar::where('id_guru', $ujianKelas->id_guru_pengampu)
                ->where('id_kelas', $ujianKelas->id_kelas)
                ->where('id_pelajaran', $ujian->id_pelajaran)->first()
            : Ngajar::where('id_kelas', $ujianKelas->id_kelas)
                ->where('id_pelajaran', $ujian->id_pelajaran)->first();
    }

    /**
     * Kebalikan dari transfer() — dipanggil saat attempt yg SUDAH ditransfer
     * (status_transfer_nilai==='berhasil') direset/dibuka-kembali aksesnya (lihat
     * UjianMonitorController::resetAttempt()), supaya nilai lama tak "nyantol" diam2 di
     * buku nilai padahal attempt sumbernya sudah dibatalkan. SENGAJA tak menghormati kunci
     * RaporKonfirmasi di sini — menghapus nilai basi milik attempt yg baru dibatalkan adalah
     * perbaikan integritas data, bukan penulisan nilai baru.
     */
    public function revert(UjianAttempt $attempt): void
    {
        if ($attempt->status_transfer_nilai !== 'berhasil') {
            return;
        }

        $ujianKelas = $attempt->ujianKelas;
        $ujian = $ujianKelas?->ujian;
        $siswa = Siswa::where('id_login', $attempt->id_siswa)->first();
        if (!$ujianKelas || !$ujian || !$siswa) {
            $attempt->update(['status_transfer_nilai' => 'belum']);
            return;
        }

        if ($ujian->target_nilai === 'sumatif') {
            $materi = Materi::find($ujian->id_materi);
            if ($materi) {
                NilaiSumatif::where('id_materi', $materi->uuid)->where('id_siswa', $siswa->uuid)->delete();
            }
        } else {
            $ngajar = $this->resolveNgajar($ujian, $ujianKelas);
            $semester = Semester::aktif();
            if ($ngajar && $semester) {
                $model = $ujian->target_nilai === 'pts' ? NilaiPts::class : NilaiPas::class;
                $model::where('id_ngajar', $ngajar->uuid)->where('id_siswa', $siswa->uuid)->where('id_semester', $semester->id)->delete();
            }
        }

        $attempt->update(['status_transfer_nilai' => 'belum']);
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
