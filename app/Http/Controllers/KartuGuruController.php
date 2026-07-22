<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Setting;
use App\Support\FotoKartu;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/*
| Kartu ID Guru — generate kartu identitas pegawai otomatis dari data guru.
| Desain kartu portrait (54 × 85,6 mm) bertema biru: logo + nama sekolah,
| foto guru (diunggah admin, tersimpan di kolom guru.foto), nama, jabatan
| otomatis dari role akun (teks besar di background ala "DIVISI"), dan QR
| berisi NIP/NIK/UUID guru. Admin only (permission manage_users).
*/
class KartuGuruController extends Controller
{
    /** Daftar guru + status foto, pencarian, tombol lihat/cetak. */
    public function kelola(Request $request)
    {
        abort_unless(auth()->user()->canAccess('manage_users'), 403);

        $q = trim((string) $request->input('q', ''));

        $gurus = Guru::with(['user', 'walikelas.kelas'])
            ->when($q !== '', fn ($query) => $query->where(fn ($sub) =>
                $sub->where('nama', 'like', "%{$q}%")
                    ->orWhere('nip', 'like', "%{$q}%")
                    ->orWhere('nik', 'like', "%{$q}%")))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        $jabatans = $gurus->getCollection()
            ->mapWithKeys(fn (Guru $g) => [$g->uuid => $this->jabatan($g)]);

        return view('kartu-guru.kelola', compact('gurus', 'q', 'jabatans'));
    }

    /**
     * Unggah / ganti foto kartu seorang guru (tersimpan di kolom guru.foto).
     * Foto SELALU dikompres+diresize dulu (lihat FotoKartu) — foto ponsel mentah bisa 3-8MB;
     * disematkan mentah utk puluhan guru sekaligus di "Cetak Semua" bikin render PDF sangat
     * lambat. Sebagai efek samping ini juga lebih aman: file DIBANGUN ULANG dari piksel via GD
     * (bukan cuma disalin), jadi payload apa pun yang menumpang di file asli ikut hilang.
     */
    public function fotoStore(Request $request, Guru $guru)
    {
        abort_unless(auth()->user()->canAccess('manage_users'), 403);

        $request->validate([
            'foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:8192',
        ], [], ['foto' => 'foto guru']);

        if ($guru->foto && Storage::disk('public')->exists($guru->foto)) {
            Storage::disk('public')->delete($guru->foto);
        }

        $file = $request->file('foto');
        // PNG/WEBP diasumsikan foto cutout transparan (disimpan sbg PNG); JPEG foto biasa (lebih
        // kecil sbg JPEG). Ditentukan dari validasi MIME Laravel, BUKAN ekstensi klaim klien.
        $preserveAlpha = in_array(strtolower($file->getClientOriginalExtension()), ['png', 'webp'], true);
        $ext = $preserveAlpha ? 'png' : 'jpg';

        $binary = FotoKartu::resize(file_get_contents($file->getRealPath()), $preserveAlpha);
        $path = 'guru-foto/'.$guru->uuid.'_'.now()->format('YmdHis').'.'.$ext;
        Storage::disk('public')->put($path, $binary);
        $guru->update(['foto' => $path]);

        return back()->with('success', 'Foto '.$guru->nama.' disimpan.');
    }

    /** Hapus foto kartu seorang guru. */
    public function fotoHapus(Guru $guru)
    {
        abort_unless(auth()->user()->canAccess('manage_users'), 403);

        if ($guru->foto && Storage::disk('public')->exists($guru->foto)) {
            Storage::disk('public')->delete($guru->foto);
        }
        $guru->update(['foto' => null]);

        return back()->with('success', 'Foto '.$guru->nama.' dihapus.');
    }

    /** Kartu satu guru sebagai PDF (kertas persis ukuran kartu, siap cetak/unduh). */
    public function lihat(Guru $guru)
    {
        abort_unless(auth()->user()->canAccess('manage_users'), 403);

        // 54 × 85,6 mm dalam point (1 mm = 72/25.4 pt)
        $paper = [0, 0, 54 * 72 / 25.4, 85.6 * 72 / 25.4];

        return Pdf::loadView('kartu-guru.pdf', ['card' => $this->cardData($guru)] + $this->sharedData())
            ->setPaper($paper)
            ->stream('Kartu ID - '.$guru->nama.'.pdf');
    }

    /**
     * Kartu ID digital milik guru yang sedang login — bisa dilihat di HP kapan saja,
     * lengkap dengan tombol "Perbesar QR" utk ditunjukkan ke kamera kiosk saat absen
     * (kamera /absensi/scan sudah membaca QR kartu, lihat AbsensiController::markByBarcode).
     */
    public function self()
    {
        $guru = auth()->user()->guru;
        abort_unless($guru, 403, 'Halaman ini khusus untuk akun guru.');

        return view('kartu-guru.self', ['card' => $this->cardData($guru)] + $this->sharedData());
    }

    /**
     * Cetak massal semua guru: A4 potret, 9 kartu (3×3) per halaman, depan lalu belakang.
     * Langsung DIUNDUH (bukan ditampilkan inline) — dokumen bisa puluhan halaman dgn banyak
     * foto sekalipun sudah dikompres, jadi lebih nyaman diunduh lalu dibuka/print offline drpd
     * menunggu browser merender PDF besar inline.
     */
    public function cetakSemua()
    {
        abort_unless(auth()->user()->canAccess('manage_users'), 403);

        $gurus = Guru::with(['user', 'walikelas.kelas'])->orderBy('nama')->get();
        abort_if($gurus->isEmpty(), 404, 'Belum ada data guru.');

        $cards = $gurus->map(fn (Guru $g) => $this->cardData($g));

        return Pdf::loadView('kartu-guru.cetak-massal', [
            'pages' => $cards->chunk(9)->values(),
            'total' => $gurus->count(),
        ] + $this->sharedData())
            ->setPaper('a4', 'portrait')
            ->download('kartu-id-guru.pdf');
    }

    // ── Data kartu ────────────────────────────────────────────────────────

    /** Identitas sekolah + logo (sama untuk semua kartu). */
    private function sharedData(): array
    {
        return [
            'sekolah' => [
                'nama'   => Setting::get('nama_sekolah', 'Sekolah'),
                'npsn'   => Setting::get('npsn'),
                'alamat' => trim((string) (Setting::get('alamat_sekolah', '').' '.Setting::get('kota', ''))),
            ],
            'logoUri' => $this->fileToDataUri(Setting::get('sekolah_logo')),
        ];
    }

    /** Payload satu kartu: guru + jabatan (label & teks background) + foto + QR. */
    private function cardData(Guru $guru): array
    {
        $guru->loadMissing(['user', 'walikelas.kelas']);
        [$label, $bgText] = $this->jabatan($guru);

        $qrPayload = $guru->nip ?: ($guru->nik ?: $guru->uuid);

        return [
            'guru'       => $guru,
            'jabatan'    => $label,
            'bgText'     => $bgText,
            'fotoUri'    => $this->fileToDataUri($guru->foto),
            'qrUri'      => 'data:image/svg+xml;base64,'.base64_encode(
                QrCode::format('svg')->size(160)->margin(0)->generate((string) $qrPayload)
            ),
            'qrPayload'  => (string) $qrPayload,
            'nomor'      => $guru->nip ? 'NIP '.$guru->nip : ($guru->nik ? 'NIK '.$guru->nik : ''),
        ];
    }

    /**
     * Jabatan otomatis dari role akun guru: [label lengkap, teks besar background].
     * Wali kelas ditambahkan sebagai keterangan pada label (bukan teks background).
     */
    private function jabatan(Guru $guru): array
    {
        $access = $guru->user?->access;

        [$label, $bg] = match ($access) {
            'kepala'               => ['Kepala Sekolah', 'KEPSEK'],
            'kurikulum'            => ['Waka Kurikulum', 'KURIKULUM'],
            'kesiswaan'            => ['Waka Kesiswaan', 'KESISWAAN'],
            'sarpras'              => ['Waka Sarpras', 'SARPRAS'],
            'bendahara'            => ['Bendahara', 'BENDAHARA'],
            'admin', 'superadmin'  => ['Admin / Operator', 'ADMIN'],
            'yayasan'              => ['Yayasan', 'YAYASAN'],
            default                => ['Guru', 'GURU'],
        };

        $wk = $guru->walikelas?->kelas;
        if ($wk) {
            $label .= ' • Wali Kelas '.$wk->tingkat.$wk->kelas;
        }

        return [$label, $bg];
    }

    /** Ubah file di disk publik menjadi data-URI base64 (aman di-embed dompdf). */
    private function fileToDataUri(?string $rel): ?string
    {
        if (empty($rel)) {
            return null;
        }
        if (str_starts_with($rel, 'data:')) {
            return $rel;
        }
        $path = storage_path('app/public/'.ltrim($rel, '/'));
        if (! is_file($path)) {
            return null;
        }
        $mime = @mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }
}
