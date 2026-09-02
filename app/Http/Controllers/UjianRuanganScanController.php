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
 * lokasi — bukan penugasan administratif. Siswa scan SEKALI mengisi hadir utk
 * SEMUA mapel hari itu sekaligus (bukan cuma sesi yg sedang jalan) — dipakai jg
 * sbg gate akses ambil-ujian kalau paket ini UjianPaket::wajib_scan_qr (lihat
 * UjianPolicy::take()/UjianSiswaController::gate()).
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

        $sesiList = $this->resolveSesiUntukCheckin($ruangan, $siswa);
        abort_unless($sesiList->isNotEmpty(), 404, 'Belum bisa menentukan sesi ujian yang sesuai untuk Anda saat ini — hubungi pengawas.');

        // firstOrCreate per sesi: satu scan langsung mengisi hadir utk SEMUA mapel hari
        // ini yg eligible kelasnya (dipakai jg utk buka akses UjianPaket::wajib_scan_qr
        // seharian sekali scan — lihat UjianPaket::sudahDicekSiswa()). Scan berulang TIDAK
        // menimpa status yg sudah tercatat (mis. kalau pengawas sempat koreksi manual).
        $hadirList = $sesiList->map(fn (UjianSesi $sesi) => UjianDaftarHadir::firstOrCreate(
            ['id_ruangan' => $ruangan->uuid, 'id_siswa' => $siswa->uuid, 'id_sesi' => $sesi->uuid],
            ['status' => 'hadir', 'tanggal' => $sesi->tanggal->toDateString(), 'dicatat_oleh' => $siswa->id_login, 'dicatat_pada' => now()]
        ));

        return view('ujian.ruangan.checkin', [
            'ruangan' => $ruangan,
            'siswa' => $siswa,
            'hadir' => $hadirList->first(),
            'sesiList' => $sesiList,
            'baruSajaDicatat' => $hadirList->contains->wasRecentlyCreated,
        ]);
    }

    /**
     * Resolusi SEMUA sesi hari ini yg relevan bagi siswa ini scan — dipakai baik utk
     * mengisi daftar hadir per-sesi (dokumen resmi) MAUPUN (via UjianPaket::sudahDicekSiswa())
     * utk membuka akses ambil-ujian seharian sekaligus kalau paket ini wajib_scan_qr, jadi
     * SENGAJA tak dipersempit ke satu sesi (tie-break jam) lagi spt sebelumnya — satu scan
     * di pagi hari harus menutupi semua mapel hari itu, bukan cuma sesi yg sedang jalan saat
     * itu. Dicocokkan lewat kelas siswa vs UjianKelas tiap sesi (query sama dgn
     * jumlahPesertaSeharusnya) supaya siswa TIDAK salah tercatat ke sesi mapel yg bukan
     * diikutinya (mis. 2 sesi jam identik, mapel beda, kelas beda — kasus nyata produksi).
     */
    private function resolveSesiUntukCheckin(UjianRuangan $ruangan, Siswa $siswa): \Illuminate\Support\Collection
    {
        $sesiHariIni = $ruangan->sesiPada();
        if ($sesiHariIni->isEmpty()) {
            return collect();
        }

        $sesiCocokMapel = $sesiHariIni->filter(
            fn (UjianSesi $s) => UjianKelas::whereIn('id_ujian', $s->jadwal->pluck('id_ujian'))
                ->where('id_kelas', $siswa->id_kelas)
                ->exists()
        );

        if ($sesiCocokMapel->isNotEmpty()) {
            return $sesiCocokMapel->values();
        }

        // Tak ada sesi yg cocok kelasnya (data UjianKelas blm lengkap) — fallback aman
        // kalau ruangan cuma py 1 sesi hari itu, kalau >1 ambigu, jangan menebak (404).
        return $sesiHariIni->count() === 1 ? $sesiHariIni : collect();
    }
}
