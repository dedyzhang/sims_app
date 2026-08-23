<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use App\Models\UjianBeritaAcara;
use App\Models\UjianDaftarHadir;
use App\Models\UjianKelas;
use App\Models\UjianRuangan;
use App\Models\UjianSesi;
use Illuminate\Http\Request;

/**
 * Satu titik masuk QR per ruangan (ditempel fisik di ruangan, lihat tombol
 * "Tampilkan QR" di ujian/paket/ruangan/show.blade.php) — siswa scan utk catat
 * hadir sendiri (self check-in), guru scan utk masuk halaman monitor DAN
 * otomatis tercatat sbg pengawas berita acara sesi yg SEDANG berjalan (jam
 * scan berada di antara jam_mulai/jam_selesai sesi itu — lihat
 * UjianRuangan::sesiAktifSekarang()). TIDAK ADA penugasan pengawas tersimpan
 * di muka — otorisasi guru murni via UjianRuanganPolicy::awasi() (guru mana
 * pun boleh, asal ruangan ini py ujian dijadwalkan hari itu). "Bukti hadir di
 * ruangan" = tahu URL scan-nya, yg cuma bisa didapat dgn scan fisik QR di
 * lokasi — bukan penugasan administratif.
 */
class UjianRuanganScanController extends Controller
{
    public function scan(Request $request, UjianRuangan $ruangan)
    {
        $user = $request->user();

        if ($siswa = $user->siswa) {
            return $this->checkinSiswa($ruangan, $siswa);
        }

        $this->authorize('awasi', $ruangan);
        $this->catatPengawasSesiAktif($ruangan, $user->guru);

        return redirect()->route('ujian.ruangan.monitor', $ruangan)
            ->with('success', 'Berhasil masuk sebagai pengawas ruangan ' . $ruangan->nama . '.');
    }

    /**
     * Kalau ada sesi yg SEDANG berjalan (jam scan masuk jendela jam_mulai/jam_selesai-nya)
     * DAN guru yg scan py profil Guru (bukan admin murni tanpa profil guru), catat/perbarui
     * id_guru_pengawas berita acara sesi itu — pengawas TERAKHIR yg scan yg tercatat, supaya
     * kalau ada pergantian pengawas di tengah sesi, catatan ikut berpindah ke yg terbaru.
     * No-op diam2 kalau tak ada sesi aktif (mis. scan di luar jam ujian) atau user bukan guru.
     */
    private function catatPengawasSesiAktif(UjianRuangan $ruangan, $guru): void
    {
        if (!$guru) {
            return;
        }
        $sesi = $ruangan->sesiAktifSekarang();
        if (!$sesi) {
            return;
        }

        UjianBeritaAcara::updateOrCreate(
            ['id_ruangan' => $ruangan->uuid, 'id_sesi' => $sesi->uuid],
            ['id_guru_pengawas' => $guru->uuid, 'tanggal' => $sesi->tanggal->toDateString()]
        );
    }

    private function checkinSiswa(UjianRuangan $ruangan, Siswa $siswa)
    {
        $peserta = $ruangan->peserta()->where('id_siswa', $siswa->uuid)->first();
        abort_unless($peserta, 403, 'Anda tidak terdaftar sebagai peserta di ruangan ini — hubungi pengawas.');

        $sesi = $this->resolveSesiUntukCheckin($ruangan, $siswa);
        abort_unless($sesi, 404, 'Belum bisa menentukan sesi ujian yang sesuai untuk Anda saat ini — hubungi pengawas.');

        // firstOrCreate: scan berulang TIDAK menimpa status yg sudah tercatat (mis.
        // kalau pengawas sempat koreksi manual) — cuma catat sekali per sesi.
        $hadir = UjianDaftarHadir::firstOrCreate(
            ['id_ruangan' => $ruangan->uuid, 'id_siswa' => $siswa->uuid, 'id_sesi' => $sesi->uuid],
            ['status' => 'hadir', 'tanggal' => $sesi->tanggal->toDateString(), 'dicatat_oleh' => $siswa->id_login, 'dicatat_pada' => now()]
        );

        return view('ujian.ruangan.checkin', [
            'ruangan' => $ruangan,
            'siswa' => $siswa,
            'hadir' => $hadir,
            'baruSajaDicatat' => $hadir->wasRecentlyCreated,
        ]);
    }

    /**
     * Resolusi sesi mana yg dimaksud siswa ini scan — TIDAK bisa pakai
     * sesiAktifSekarang() polos (tie-break jam doang) krn produksi py kasus nyata
     * 2 sesi jam IDENTIK (mapel beda) di hari yg sama; siswa yg ikut mapel A bisa
     * salah tercatat ke sesi mapel B kalau cuma ditie-break dari jam. Dicocokkan
     * lewat kelas siswa vs UjianKelas tiap sesi (query sama dgn
     * jumlahPesertaSeharusnya) — kalau eligible di >1 sesi, jam aktif jadi
     * tie-break; kalau ruangan cuma py 1 sesi hari itu, langsung pakai itu.
     */
    private function resolveSesiUntukCheckin(UjianRuangan $ruangan, Siswa $siswa): ?UjianSesi
    {
        $sesiHariIni = $ruangan->sesiPada();
        if ($sesiHariIni->isEmpty()) {
            return null;
        }

        $sesiCocokMapel = $sesiHariIni->filter(
            fn (UjianSesi $s) => UjianKelas::whereIn('id_ujian', $s->jadwal->pluck('id_ujian'))
                ->where('id_kelas', $siswa->id_kelas)
                ->exists()
        );

        if ($sesiCocokMapel->count() === 1) {
            return $sesiCocokMapel->first();
        }

        if ($sesiCocokMapel->count() > 1) {
            $sekarang = now()->format('H:i:s');
            $aktif = $sesiCocokMapel
                ->filter(fn (UjianSesi $s) => $s->jam_mulai <= $sekarang && $sekarang <= $s->jam_selesai)
                ->sortByDesc('jam_mulai')->first();

            return $aktif ?? $sesiCocokMapel->sortByDesc('jam_mulai')->first();
        }

        // Tak ada sesi yg cocok kelasnya (data UjianKelas blm lengkap) — fallback aman
        // kalau ruangan cuma py 1 sesi hari itu, kalau >1 tak bisa ditebak, gagal 404.
        return $sesiHariIni->count() === 1 ? $sesiHariIni->first() : null;
    }
}
