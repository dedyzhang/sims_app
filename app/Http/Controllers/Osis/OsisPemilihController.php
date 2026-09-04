<?php

namespace App\Http\Controllers\Osis;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\OsisPemilih;
use App\Models\OsisPemilihan;
use App\Models\Setting;
use App\Models\Siswa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class OsisPemilihController extends Controller
{
    /** Generate token utk SEMUA siswa 1 kelas sekaligus — SATU query upsert, bukan loop create(). */
    public function generateTokenKelas(OsisPemilihan $pemilihan, Request $request)
    {
        $data = $request->validate(['id_kelas' => 'required|uuid|exists:kelas,uuid']);
        $kelas = Kelas::findOrFail($data['id_kelas']);

        $siswaList = Siswa::where('id_kelas', $kelas->uuid)->select('uuid', 'nama', 'nis')->orderBy('nama')->get();
        abort_if($siswaList->isEmpty(), 422, 'Kelas ini belum punya siswa.');

        // Siswa yg SUDAH py token di periode ini — jangan digenerate ulang (token lama tetap berlaku).
        $sudahAda = OsisPemilih::where('id_pemilihan', $pemilihan->uuid)
            ->where('tipe_pemilih', 'siswa')
            ->whereIn('id_siswa', $siswaList->pluck('uuid'))
            ->pluck('id_siswa')->flip();

        $kelasLabel = trim($kelas->tingkat.$kelas->kelas);
        $now = now();
        $rows = $siswaList->reject(fn ($s) => $sudahAda->has($s->uuid))->map(fn ($s) => [
            'uuid' => (string) Str::orderedUuid(), // HasUuids TAK jalan di upsert(), pola sama KalenderController
            'id_pemilihan' => $pemilihan->uuid,
            'tipe_pemilih' => 'siswa',
            'id_siswa' => $s->uuid,
            'id_guru' => null,
            'nama_snapshot' => $s->nama,
            'nis_snapshot' => $s->nis,
            'kelas_snapshot' => $kelasLabel,
            'token' => Str::random(32),
            'id_paslon_dipilih' => null,
            'sudah_memilih_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();

        if (! empty($rows)) {
            // Kolom ke-3 kosong = jangan timpa baris yg SUDAH ada (safety net thd 2 klik generate hampir bersamaan).
            OsisPemilih::upsert($rows, ['id_pemilihan', 'id_siswa'], []);
        }

        return back()->with('success', count($rows)." token pemilih baru dibuat untuk kelas {$kelasLabel} (".$sudahAda->count().' sudah ada sebelumnya).');
    }

    /** Generate token utk SEMUA guru sekaligus — SATU query upsert. */
    public function generateTokenGuru(OsisPemilihan $pemilihan)
    {
        $guruList = Guru::select('uuid', 'nama')->orderBy('nama')->get();
        $sudahAda = OsisPemilih::where('id_pemilihan', $pemilihan->uuid)->where('tipe_pemilih', 'guru')->pluck('id_guru')->flip();

        $now = now();
        $rows = $guruList->reject(fn ($g) => $sudahAda->has($g->uuid))->map(fn ($g) => [
            'uuid' => (string) Str::orderedUuid(),
            'id_pemilihan' => $pemilihan->uuid,
            'tipe_pemilih' => 'guru',
            'id_siswa' => null,
            'id_guru' => $g->uuid,
            'nama_snapshot' => $g->nama,
            'nis_snapshot' => null,
            'kelas_snapshot' => null,
            'token' => Str::random(32),
            'id_paslon_dipilih' => null,
            'sudah_memilih_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();

        if (! empty($rows)) {
            OsisPemilih::upsert($rows, ['id_pemilihan', 'id_guru'], []);
        }

        return back()->with('success', count($rows).' token pemilih guru baru dibuat.');
    }

    /**
     * Cetak QR 1 kelas: A4 potret, tabel SATU KOLOM baris (persis gaya "lembar absensi"),
     * tiap baris = 1 siswa.
     *
     * Estimasi awal "10 baris/halaman (QR 20mm) & 12 baris/halaman (QR 17mm)" TERBUKTI
     * SALAH saat dirender sungguhan lewat dompdf — tinggi baris asli lebih besar dari
     * perkiraan mm manual, jadi cuma 8 (QR 20mm) / 9 (QR 17mm) baris yg BENAR2 muat, sisanya
     * meluber ke halaman baru yg nyaris kosong (kertas terbuang). Angka 8/9 di sini bukan
     * hasil hitung ulang di atas kertas, tapi diverifikasi lewat render PDF sungguhan (bukan
     * cuma dihitung manual lagi — supaya tak mengulang kesalahan yg sama).
     * QR 17mm masih dlm rentang scannable (terbukti di kartu-pelajar cetak-massal yg pakai
     * QR 15mm). Default 8/lembar.
     */
    public function cetakKelas(OsisPemilihan $pemilihan, Kelas $kelas, Request $request)
    {
        $perHalaman = (int) $request->input('per_halaman', 8);
        $perHalaman = in_array($perHalaman, [8, 9], true) ? $perHalaman : 8;

        $pemilihList = OsisPemilih::where('id_pemilihan', $pemilihan->uuid)
            ->where('tipe_pemilih', 'siswa')
            ->whereHas('siswa', fn ($q) => $q->where('id_kelas', $kelas->uuid))
            ->with('siswa:uuid,nama,nis') // 2 query total (bukan N+1): 1 main + 1 eager-load
            ->get()
            ->sortBy('siswa.nama')
            ->values();

        abort_if($pemilihList->isEmpty(), 404, 'Belum ada token pemilih utk kelas ini — generate dulu.');

        $baseUrl = route('osis.publik.show', ['token' => '__TOKEN__']);
        $rows = $pemilihList->map(fn ($p) => [
            'nama' => $p->siswa->nama ?? $p->nama_snapshot,
            'nis' => $p->siswa->nis ?? $p->nis_snapshot,
            'qrUri' => 'data:image/svg+xml;base64,'.base64_encode(
                QrCode::format('svg')->size(220)->margin(1)->generate(str_replace('__TOKEN__', $p->token, $baseUrl))
            ),
        ]);

        return Pdf::loadView('osis.admin.cetak-massal', [
            'pages' => $rows->chunk($perHalaman)->values(),
            'perHalaman' => $perHalaman,
            'kelas' => $kelas,
            'pemilihan' => $pemilihan,
            'sekolah' => [
                'nama' => Setting::get('nama_sekolah', 'Sekolah'),
                'npsn' => Setting::get('npsn'),
            ],
        ])->setPaper('a4', 'portrait')->stream("qr-osis-{$kelas->tingkat}{$kelas->kelas}.pdf");
    }

    public function cetakGuru(OsisPemilihan $pemilihan, Request $request)
    {
        $perHalamanInput = (int) $request->input('per_halaman', 8);
        $perHalaman = in_array($perHalamanInput, [8, 9], true) ? $perHalamanInput : 8;

        $pemilihList = OsisPemilih::where('id_pemilihan', $pemilihan->uuid)
            ->where('tipe_pemilih', 'guru')
            ->with('guru:uuid,nama,nip')
            ->get()->sortBy('guru.nama')->values();

        abort_if($pemilihList->isEmpty(), 404, 'Belum ada token pemilih guru — generate dulu.');

        $baseUrl = route('osis.publik.show', ['token' => '__TOKEN__']);
        $rows = $pemilihList->map(fn ($p) => [
            'nama' => $p->guru->nama ?? $p->nama_snapshot,
            'nis' => $p->guru->nip ?? '-',
            'qrUri' => 'data:image/svg+xml;base64,'.base64_encode(
                QrCode::format('svg')->size(220)->margin(1)->generate(str_replace('__TOKEN__', $p->token, $baseUrl))
            ),
        ]);

        return Pdf::loadView('osis.admin.cetak-massal', [
            'pages' => $rows->chunk($perHalaman)->values(),
            'perHalaman' => $perHalaman,
            'kelas' => null, 'pemilihan' => $pemilihan,
            'sekolah' => ['nama' => Setting::get('nama_sekolah', 'Sekolah'), 'npsn' => Setting::get('npsn')],
        ])->setPaper('a4', 'portrait')->stream('qr-osis-guru.pdf');
    }

    /**
     * Daftar hadir 1 kelas: SATU tabel teks (tanpa QR, jadi baris jauh lebih pendek dari
     * cetakKelas), status "Sudah/Belum Memilih" TERISI OTOMATIS dari sudah_memilih_at —
     * bukan lembar tanda tangan manual. ±30 siswa/kelas di sekolah ini muat jauh di bawah
     * 1 halaman A4 dgn baris sepadat ini (pola sama ujian/ruangan/_hadirBody.blade.php yg
     * terbukti muat 1 kelas penuh), jadi TIDAK di-chunk seperti cetakKelas.
     */
    public function cetakAbsensiKelas(OsisPemilihan $pemilihan, Kelas $kelas)
    {
        $pemilihList = OsisPemilih::where('id_pemilihan', $pemilihan->uuid)
            ->where('tipe_pemilih', 'siswa')
            ->whereHas('siswa', fn ($q) => $q->where('id_kelas', $kelas->uuid))
            ->with('siswa:uuid,nama,nis')
            ->get()
            ->sortBy(fn ($p) => $p->siswa->nama ?? $p->nama_snapshot)
            ->values();

        abort_if($pemilihList->isEmpty(), 404, 'Belum ada token pemilih utk kelas ini — generate dulu.');

        $rows = $pemilihList->map(fn ($p) => [
            'nama' => $p->siswa->nama ?? $p->nama_snapshot,
            'nis' => $p->siswa->nis ?? $p->nis_snapshot,
            'sudah' => $p->sudahMemilih(),
            'waktu' => $p->sudah_memilih_at?->translatedFormat('d/m/y H:i'),
        ]);

        return Pdf::loadView('osis.admin.cetak-absensi', $this->kopData() + [
            'rows' => $rows,
            'kelas' => $kelas,
            'pemilihan' => $pemilihan,
            'labelIdentitas' => 'NIS',
            'judulKelompok' => 'Kelas '.$kelas->tingkat.$kelas->kelas,
        ])->setPaper('a4', 'portrait')->stream("absensi-osis-{$kelas->tingkat}{$kelas->kelas}.pdf");
    }

    public function cetakAbsensiGuru(OsisPemilihan $pemilihan)
    {
        $pemilihList = OsisPemilih::where('id_pemilihan', $pemilihan->uuid)
            ->where('tipe_pemilih', 'guru')
            ->with('guru:uuid,nama,nip')
            ->get()
            ->sortBy(fn ($p) => $p->guru->nama ?? $p->nama_snapshot)
            ->values();

        abort_if($pemilihList->isEmpty(), 404, 'Belum ada token pemilih guru — generate dulu.');

        $rows = $pemilihList->map(fn ($p) => [
            'nama' => $p->guru->nama ?? $p->nama_snapshot,
            'nis' => $p->guru->nip ?? '-',
            'sudah' => $p->sudahMemilih(),
            'waktu' => $p->sudah_memilih_at?->translatedFormat('d/m/y H:i'),
        ]);

        return Pdf::loadView('osis.admin.cetak-absensi', $this->kopData() + [
            'rows' => $rows,
            'kelas' => null,
            'pemilihan' => $pemilihan,
            'labelIdentitas' => 'NIP',
            'judulKelompok' => 'Guru & Karyawan',
        ])->setPaper('a4', 'portrait')->stream('absensi-osis-guru.pdf');
    }

    /** Pola sama persis UjianRekapController::kopData() — kop surat + kepsek dari Guru ber-akses 'kepala'. */
    private function kopData(): array
    {
        $kepsek = Guru::whereHas('user', fn ($q) => $q->where('access', 'kepala'))->first();

        return [
            'namaSekolah' => Setting::get('nama_sekolah', ''),
            'alamatSekolah' => Setting::get('alamat_sekolah', ''),
            'kopTeks' => Setting::get('kop_teks'),
            'kopLogoKiri' => $this->kopImgDataUri('kop_logo_kiri', 'img/tutwuri.png'),
            'kopLogoKanan' => $this->kopImgDataUri('kop_logo_kanan', 'img/maitreyawira_square.png'),
            'kepsekNama' => $kepsek?->nama ?? Setting::get('kepala_sekolah', ''),
        ];
    }

    private function kopImgDataUri(string $key, string $default): ?string
    {
        $v = Setting::get($key);
        if ($v && Storage::disk('public')->exists($v)) {
            return $this->fileToDataUri(Storage::disk('public')->path($v));
        }
        if (file_exists(public_path($default))) {
            return $this->fileToDataUri(public_path($default));
        }

        return null;
    }

    private function fileToDataUri(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }
        $mime = @mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }

    /** Roster detail per kelas — LAZY, dipanggil manual admin (bukan auto-poll), paginated. */
    public function rosterKelas(OsisPemilihan $pemilihan, Kelas $kelas)
    {
        $roster = OsisPemilih::where('id_pemilihan', $pemilihan->uuid)
            ->where('tipe_pemilih', 'siswa')
            ->whereHas('siswa', fn ($q) => $q->where('id_kelas', $kelas->uuid))
            ->with('siswa:uuid,nama,nis')
            ->orderBy('sudah_memilih_at')
            ->paginate(30);

        return response()->json([
            'ok' => true,
            'roster' => $roster->through(fn ($p) => [
                'nama' => $p->siswa->nama ?? $p->nama_snapshot,
                'nis' => $p->siswa->nis ?? $p->nis_snapshot,
                'sudah' => $p->sudahMemilih(),
                'pada' => optional($p->sudah_memilih_at)?->toIso8601String(),
            ]),
        ]);
    }
}
