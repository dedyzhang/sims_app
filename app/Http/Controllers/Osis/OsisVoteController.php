<?php

namespace App\Http\Controllers\Osis;

use App\Http\Controllers\Controller;
use App\Models\OsisPaslon;
use App\Models\OsisPemilih;
use App\Models\OsisPemilihan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OsisVoteController extends Controller
{
    public function show(string $token)
    {
        $pemilih = OsisPemilih::where('token', $token)->with('pemilihan')->first();
        abort_unless($pemilih, 404, 'Kode QR tidak dikenali. Hubungi panitia pemilihan.');

        // isKiosk=true: pakai mekanisme yg SUDAH ADA di layouts/app.blade.php (~baris 20-28) —
        // sidebar/topbar/header disembunyikan, halaman tetap ikut branding sekolah (logo/warna).
        $shared = ['isKiosk' => true, 'pemilih' => $pemilih];

        if ($pemilih->sudahMemilih()) {
            return view('osis.publik.sudah-memilih', $shared);
        }
        if (! $pemilih->pemilihan || ! $pemilih->pemilihan->bolehMemilihSekarang()) {
            return view('osis.publik.belum-dibuka', $shared + [
                'status' => $pemilih->pemilihan?->statusEfektif(),
                'jadwalMulai' => $pemilih->pemilihan?->jadwal_mulai,
            ]);
        }

        $paslonList = OsisPaslon::where('id_pemilihan', $pemilih->id_pemilihan)
            ->orderBy('urutan_tampil')->orderBy('nomor_urut')->get();

        return view('osis.publik.pilih', $shared + ['paslonList' => $paslonList]);
    }

    /**
     * Guard ANTI RACE CONDITION: row `osis_pemilih` DI-LOCK (lockForUpdate) sebelum cek
     * `sudah_memilih_at`. Kalau 2 request datang nyaris bersamaan dgn token SAMA (double-tap
     * tombol / 2 tab), request kedua WAJIB menunggu transaksi pertama commit — begitu lock
     * lepas, ia baca `sudah_memilih_at` yg SUDAH terisi dari request pertama, langsung ditolak.
     * INI BEDA dari pola lama AbsensiController::markByBarcode() yg cuma cek row via query PHP
     * SEBELUM insert (2 request bisa baca "belum ada" berbarengan lalu insert berdua) — pola di
     * sini pakai lockForUpdate() sebelum cek+tulis, yg benar2 atomic.
     */
    public function store(Request $request, string $token)
    {
        $data = $request->validate(['id_paslon' => 'required|uuid']);

        $outcome = DB::transaction(function () use ($token, $data, $request) {
            $pemilih = OsisPemilih::where('token', $token)->lockForUpdate()->first();
            if (! $pemilih) {
                return ['status' => 'not_found'];
            }
            if ($pemilih->sudahMemilih()) {
                return ['status' => 'already'];
            }

            $pemilihan = OsisPemilihan::where('uuid', $pemilih->id_pemilihan)->lockForUpdate()->first();
            if (! $pemilihan || ! $pemilihan->bolehMemilihSekarang()) {
                return ['status' => 'closed'];
            }

            $valid = OsisPaslon::where('uuid', $data['id_paslon'])->where('id_pemilihan', $pemilih->id_pemilihan)->exists();
            if (! $valid) {
                return ['status' => 'invalid_paslon'];
            }

            $pemilih->forceFill([
                'id_paslon_dipilih' => $data['id_paslon'],
                'sudah_memilih_at' => now(),
                'ip_saat_memilih' => $request->ip(),
                'user_agent_saat_memilih' => substr((string) $request->userAgent(), 0, 255),
            ])->save();

            return ['status' => 'ok'];
        });

        // Post/Redirect/Get: redirect balik ke show() supaya reload/back button tak submit ulang.
        return match ($outcome['status']) {
            'ok', 'already' => redirect()->route('osis.publik.show', $token),
            'closed' => back()->withErrors(['id_paslon' => 'Pemilihan belum dibuka atau sudah ditutup.']),
            'invalid_paslon' => back()->withErrors(['id_paslon' => 'Paslon tidak valid.']),
            default => abort(404, 'Kode QR tidak dikenali.'),
        };
    }
}
