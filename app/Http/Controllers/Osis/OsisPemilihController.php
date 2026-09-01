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
     * tiap baris = 1 siswa. Area cetak ≈281mm, kop+header ≈35mm, sisa ≈246mm utk baris:
     * 10 baris/halaman → QR 20mm; 12 baris/halaman → QR 17mm (masih dlm rentang scannable,
     * terbukti di kartu-pelajar cetak-massal yg pakai QR 15mm). Default 10/lembar.
     */
    public function cetakKelas(OsisPemilihan $pemilihan, Kelas $kelas, Request $request)
    {
        $perHalaman = (int) $request->input('per_halaman', 10);
        $perHalaman = in_array($perHalaman, [10, 12], true) ? $perHalaman : 10;

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
        $perHalamanInput = (int) $request->input('per_halaman', 10);
        $perHalaman = in_array($perHalamanInput, [10, 12], true) ? $perHalamanInput : 10;

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
