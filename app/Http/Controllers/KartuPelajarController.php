<?php

namespace App\Http\Controllers;

use App\Models\KartuPelajar;
use App\Models\Kelas;
use App\Models\Setting;
use App\Models\Siswa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/*
| Kartu Pelajar Digital.
| - Admin (permission manage_users): mengunggah/ mengganti/ menghapus kartu tiap siswa.
| - Siswa: melihat & mengunduh kartunya sendiri.
| File disimpan di disk privat `local` (storage/app/private/kartu-pelajar) dan hanya
| bisa diakses lewat route ber-auth ini — tak ada URL publik langsung.
*/
class KartuPelajarController extends Controller
{
    private const EXT = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    // ── Sisi Admin ────────────────────────────────────────────────────────

    /** Daftar siswa + status kartu, dengan pencarian & filter kelas. */
    public function kelola(Request $request)
    {
        abort_unless(auth()->user()->canAccess('manage_users'), 403);

        $q       = trim((string) $request->input('q', ''));
        $kelasId = $request->input('kelas');

        $siswa = Siswa::with(['kelas', 'kartuPelajar'])
            ->when($q !== '', fn ($query) => $query->where(fn ($sub) =>
                $sub->where('nama', 'like', "%{$q}%")
                    ->orWhere('nis', 'like', "%{$q}%")
                    ->orWhere('nisn', 'like', "%{$q}%")))
            ->when($kelasId, fn ($query) => $query->where('id_kelas', $kelasId))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        $kelas = Kelas::orderBy('tingkat')->orderBy('kelas')->get();

        return view('kartu-pelajar.kelola', compact('siswa', 'kelas', 'q', 'kelasId'));
    }

    /** Unggah / ganti kartu seorang siswa. */
    public function store(Request $request, Siswa $siswa)
    {
        abort_unless(auth()->user()->canAccess('manage_users'), 403);

        $request->validate([
            'kartu' => ['required', 'file', 'max:8192', function ($attr, $value, $fail) {
                if (! in_array(strtolower($value->getClientOriginalExtension()), self::EXT, true)) {
                    $fail('Format kartu harus JPG, PNG, WEBP, atau PDF.');
                }
            }],
        ], [], ['kartu' => 'file kartu']);

        $file = $request->file('kartu');
        $ext  = strtolower($file->getClientOriginalExtension());

        // Hapus file lama bila ada, lalu simpan yang baru.
        $lama = $siswa->kartuPelajar;
        if ($lama && Storage::disk('local')->exists($lama->path)) {
            Storage::disk('local')->delete($lama->path);
        }

        $path = $file->storeAs('kartu-pelajar', $siswa->uuid . '_' . now()->format('YmdHis') . '.' . $ext, 'local');

        KartuPelajar::updateOrCreate(
            ['id_siswa' => $siswa->uuid],
            [
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getClientMimeType(),
                'uploaded_by'   => auth()->user()->uuid,
            ],
        );

        return back()->with('success', 'Kartu pelajar ' . $siswa->nama . ' disimpan.');
    }

    /** Hapus kartu seorang siswa (file + record). */
    public function destroy(Siswa $siswa)
    {
        abort_unless(auth()->user()->canAccess('manage_users'), 403);

        $kartu = $siswa->kartuPelajar;
        if ($kartu) {
            if (Storage::disk('local')->exists($kartu->path)) {
                Storage::disk('local')->delete($kartu->path);
            }
            $kartu->delete();
        }

        return back()->with('success', 'Kartu pelajar dihapus.');
    }

    /**
     * Pratinjau kartu seorang siswa dari sisi admin. Bila admin mengunggah kartu
     * kustom, tampilkan file itu; jika tidak, tampilkan PDF hasil generate otomatis.
     */
    public function lihatAdmin(Siswa $siswa)
    {
        abort_unless(auth()->user()->canAccess('manage_users'), 403);

        if ($siswa->kartuPelajar && Storage::disk('local')->exists($siswa->kartuPelajar->path)) {
            return $this->serve($siswa->kartuPelajar);
        }

        return $this->generatePdf($siswa)->stream('kartu-pelajar.pdf');
    }

    // ── Sisi Siswa ────────────────────────────────────────────────────────

    /** Halaman kartu milik siswa yang sedang login (otomatis dari data, atau file kustom). */
    public function self()
    {
        $siswa = auth()->user()->siswa;
        abort_unless($siswa, 403);

        $kartu = $siswa->kartuPelajar;

        return view('kartu-pelajar.self', ['siswa' => $siswa, 'kartu' => $kartu] + $this->cardData($siswa));
    }

    /** Tampilkan kartu kustom milik siswa sendiri secara inline (hanya bila diunggah admin). */
    public function lihatSelf()
    {
        $siswa = auth()->user()->siswa;
        abort_unless($siswa, 403);

        return $this->serve($siswa->kartuPelajar);
    }

    /** Unduh kartu milik siswa sendiri: file kustom bila ada, jika tidak PDF generate. */
    public function unduhSelf()
    {
        $siswa = auth()->user()->siswa;
        abort_unless($siswa, 403);

        $kartu = $siswa->kartuPelajar;
        if ($kartu && Storage::disk('local')->exists($kartu->path)) {
            return Storage::disk('local')->download($kartu->path, $kartu->original_name);
        }

        return $this->generatePdf($siswa)->download('Kartu Pelajar - ' . $siswa->nama . '.pdf');
    }

    // ── Generate kartu otomatis dari data siswa ───────────────────────────

    /** Identitas sekolah untuk kartu (dipakai kartu tunggal & cetak massal). */
    private function schoolInfo(): array
    {
        return [
            'nama'   => Setting::get('nama_sekolah', 'Sekolah'),
            'alamat' => trim((string) (Setting::get('alamat_sekolah', '') . ' ' . Setting::get('kota', ''))),
            'npsn'   => Setting::get('npsn'),
            'kota'   => Setting::get('kota'),
            'kepala' => Setting::get('kepala_sekolah'),
        ];
    }

    /** Data umum kartu (identitas sekolah + kelas + payload QR) untuk view browser. */
    private function cardData(Siswa $siswa): array
    {
        $siswa->loadMissing('kelas');

        return [
            'sekolah'    => $this->schoolInfo(),
            'kelasLabel' => $siswa->kelas ? trim($siswa->kelas->tingkat . ' ' . $siswa->kelas->kelas) : '—',
            'logoPath'   => Setting::get('sekolah_logo') ?: null,
            'qrPayload'  => $siswa->nis ?: ($siswa->nisn ?: $siswa->uuid),
        ];
    }

    /** QR (SVG data-URI) untuk seorang siswa. */
    private function qrUri(Siswa $siswa): string
    {
        $payload = $siswa->nis ?: ($siswa->nisn ?: $siswa->uuid);

        return 'data:image/svg+xml;base64,' . base64_encode(
            QrCode::format('svg')->size(120)->margin(0)->generate((string) $payload)
        );
    }

    /**
     * Cetak massal kartu pelajar per tingkat: A4 potret, 10 kartu/halaman,
     * ukuran kartu ATM (CR80, 85,6×54 mm). Admin only.
     */
    public function cetakTingkat(Request $request)
    {
        abort_unless(auth()->user()->canAccess('manage_users'), 403);

        $data = $request->validate(['tingkat' => 'required|string|max:20']);
        $tingkat = $data['tingkat'];

        $siswa = Siswa::with('kelas')
            ->whereHas('kelas', fn ($q) => $q->where('tingkat', $tingkat))
            ->orderBy('nama')
            ->get();

        abort_if($siswa->isEmpty(), 404, 'Tidak ada siswa pada tingkat ' . $tingkat . '.');

        $cards = $siswa->map(fn (Siswa $s) => ['siswa' => $s, 'qrUri' => $this->qrUri($s)]);

        return Pdf::loadView('kartu-pelajar.cetak-massal', [
            'pages'   => $cards->chunk(10)->values(),
            'sekolah' => $this->schoolInfo(),
            'logoUri' => $this->fileToDataUri(Setting::get('sekolah_logo')),
            'tingkat' => $tingkat,
            'total'   => $siswa->count(),
        ])->setPaper('a4', 'portrait')->stream('kartu-pelajar-tingkat-' . $tingkat . '.pdf');
    }

    /** Bangun PDF kartu (A6 landscape) dengan gambar & QR ter-embed sebagai data-URI. */
    private function generatePdf(Siswa $siswa)
    {
        $data = $this->cardData($siswa);
        $svg  = QrCode::format('svg')->size(240)->margin(0)->generate((string) $data['qrPayload']);

        return Pdf::loadView('kartu-pelajar.pdf', [
            'siswa'      => $siswa,
            'sekolah'    => $data['sekolah'],
            'kelasLabel' => $data['kelasLabel'],
            'logoUri'    => $this->fileToDataUri($data['logoPath']),
            'qrUri'      => 'data:image/svg+xml;base64,' . base64_encode($svg),
        ])->setPaper('a6', 'landscape');
    }

    /** Ubah file di disk publik menjadi data-URI base64 (agar aman di-embed dompdf). */
    private function fileToDataUri(?string $rel): ?string
    {
        if (empty($rel)) {
            return null;
        }
        if (str_starts_with($rel, 'data:')) {
            return $rel;
        }
        $path = storage_path('app/public/' . ltrim($rel, '/'));
        if (! is_file($path)) {
            return null;
        }
        $mime = @mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }

    // ── Helper ────────────────────────────────────────────────────────────

    /** Streaming file kartu kustom secara inline (dipakai pratinjau file unggahan). */
    private function serve(?KartuPelajar $kartu)
    {
        abort_unless($kartu && Storage::disk('local')->exists($kartu->path), 404);

        return Storage::disk('local')->response($kartu->path, $kartu->original_name, [
            'Content-Type' => $kartu->mime ?: 'application/octet-stream',
        ]);
    }
}
