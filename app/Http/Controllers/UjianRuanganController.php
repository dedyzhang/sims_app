<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Siswa;
use App\Models\UjianPaket;
use App\Models\UjianRuangan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Ruang ujian fisik di dalam satu UjianPaket — roster siswa (lintas kelas).
 * TIDAK ADA penugasan pengawas di sini — siapa yg mengawasi ditentukan lewat
 * scan QR ruangan (lihat UjianRuanganScanController). Otorisasi via
 * UjianRuanganPolicy::kelola() (BUKAN per-mapel spt Ujian biasa).
 */
class UjianRuanganController extends Controller
{
    private function pastikanMilikPaket(UjianPaket $paket, UjianRuangan $ruangan): void
    {
        abort_unless($ruangan->id_ujian_paket === $paket->uuid, 404);
    }

    public function store(Request $request, UjianPaket $paket)
    {
        $dummy = new UjianRuangan(['id_ujian_paket' => $paket->uuid]);
        $this->authorize('kelola', $dummy);

        $data = $request->validate([
            'nama'       => 'required|string|max:100',
            'kapasitas'  => 'nullable|integer|min:1',
            'keterangan' => 'nullable|string|max:500',
        ]);
        $data['id_ujian_paket'] = $paket->uuid;
        UjianRuangan::create($data);

        return back()->with('success', 'Ruangan ditambahkan.');
    }

    public function update(Request $request, UjianPaket $paket, UjianRuangan $ruangan)
    {
        $this->pastikanMilikPaket($paket, $ruangan);
        $this->authorize('kelola', $ruangan);

        $data = $request->validate([
            'nama'       => 'required|string|max:100',
            'kapasitas'  => 'nullable|integer|min:1',
            'keterangan' => 'nullable|string|max:500',
        ]);
        $ruangan->update($data);

        return back()->with('success', 'Ruangan diperbarui.');
    }

    public function destroy(UjianPaket $paket, UjianRuangan $ruangan)
    {
        $this->pastikanMilikPaket($paket, $ruangan);
        $this->authorize('kelola', $ruangan);
        $ruangan->delete();

        return back()->with('success', 'Ruangan dihapus.');
    }

    /** Halaman kelola satu ruangan — form roster peserta + QR scan utk hadir/pengawas. */
    public function show(UjianPaket $paket, UjianRuangan $ruangan)
    {
        $this->pastikanMilikPaket($paket, $ruangan);
        $this->authorize('kelola', $ruangan);

        $ruangan->load('peserta.siswa.kelas');
        $idSudahMasuk = $ruangan->peserta->pluck('id_siswa');
        $siswaTersedia = Siswa::with('kelas')
            ->whereNotIn('uuid', $idSudahMasuk->isEmpty() ? ['-'] : $idSudahMasuk)
            ->orderBy('nama')->get();

        $urlScan = route('ujian.ruangan.scan', $ruangan);
        $qrUri = 'data:image/svg+xml;base64,' . base64_encode(
            QrCode::format('svg')->size(220)->margin(1)->generate($urlScan)
        );

        return view('ujian.paket.ruangan.show', compact('paket', 'ruangan', 'siswaTersedia', 'urlScan', 'qrUri'));
    }

    /** Tambah/lepas peserta ruangan (bulk) — TIDAK menyentuh token/attempt siswa, murni roster fisik. */
    public function syncPeserta(Request $request, UjianPaket $paket, UjianRuangan $ruangan)
    {
        $this->pastikanMilikPaket($paket, $ruangan);
        $this->authorize('kelola', $ruangan);

        $data = $request->validate([
            'id_siswa'   => 'nullable|array',
            'id_siswa.*' => 'uuid|exists:siswa,uuid',
        ]);
        $idBaru = collect($data['id_siswa'] ?? []);

        foreach ($idBaru as $idSiswa) {
            $ruangan->peserta()->firstOrCreate(['id_siswa' => $idSiswa]);
        }

        return back()->with('success', $idBaru->count() . ' siswa ditambahkan ke ruangan.');
    }

    public function lepasPeserta(UjianPaket $paket, UjianRuangan $ruangan, string $peserta)
    {
        $this->pastikanMilikPaket($paket, $ruangan);
        $this->authorize('kelola', $ruangan);
        $ruangan->peserta()->where('uuid', $peserta)->delete();

        return back()->with('success', 'Siswa dilepas dari ruangan.');
    }

    /** Poster QR siap cetak/tempel — kop sekolah + langkah scan siswa & guru, gaya sama spt qr/cetak.blade.php (QrAbsensiController::cetak()). */
    public function cetak(UjianPaket $paket, UjianRuangan $ruangan)
    {
        $this->pastikanMilikPaket($paket, $ruangan);
        $this->authorize('kelola', $ruangan);

        return view('ujian.paket.ruangan.cetak', [
            'paket'         => $paket,
            'ruangan'       => $ruangan,
            'urlScan'       => route('ujian.ruangan.scan', $ruangan),
            'namaSekolah'   => Setting::get('nama_sekolah', ''),
            'alamatSekolah' => Setting::get('alamat_sekolah', ''),
            'kopTeks'       => Setting::get('kop_teks'),
            'kopLogoKiri'   => $this->kopImg('kop_logo_kiri', 'img/tutwuri.png'),
            'kopLogoKanan'  => $this->kopImg('kop_logo_kanan', 'img/maitreyawira_square.png'),
        ]);
    }

    private function kopImg(string $key, string $default): ?string
    {
        $v = Setting::get($key);
        if ($v && Storage::disk('public')->exists($v)) {
            return asset('storage/' . $v);
        }
        if (file_exists(public_path($default))) {
            return asset($default);
        }

        return null;
    }
}
