<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Models\Orangtua;
use App\Models\Siswa;
use App\Models\SppPembayaran;
use App\Services\Keuangan\SppService;
use App\Support\KeuanganBank;
use App\Support\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman tagihan SPP untuk siswa & orang tua.
 *
 * Menampilkan tagihan per bulan (Juli–Juni), nomor Virtual Account, daftar
 * bank + langkah pembayaran ala marketplace, dan unggah bukti pembayaran
 * yang nantinya diverifikasi bendahara. Semua data ter-scope ke siswa milik
 * pengguna (siswa = dirinya; ortu = anak-anaknya).
 */
class TagihanController extends Controller
{
    public function __construct(private SppService $spp) {}

    public function index(Request $request)
    {
        $children = $this->children();
        abort_if($children->isEmpty(), Response::HTTP_FORBIDDEN, 'Akun ini tidak terkait data siswa.');

        $siswa = $this->resolveSiswa($request, $children);
        $ta    = TahunAjaran::current();
        $bayar = $this->spp->forSiswa($siswa, $ta);

        return view('keuangan.tagihan.index', [
            'siswa'     => $siswa,
            'children'  => $children,
            'bayar'     => $bayar,
            'bulanList' => TahunAjaran::bulanList($ta),
            'ringkasan' => $this->spp->ringkasan($bayar),
            'ta'        => $ta,
        ]);
    }

    public function show(SppPembayaran $pembayaran)
    {
        $siswa = $this->guard($pembayaran);

        $payable = in_array($pembayaran->status, [SppPembayaran::STATUS_BELUM, SppPembayaran::STATUS_DITOLAK]);

        // Bulan lain yang juga belum dibayar (untuk fitur "bayar sekaligus").
        $lainnya = collect();
        if ($payable) {
            $lainnya = SppPembayaran::where('id_siswa', $siswa->uuid)
                ->where('tahun_ajaran', $pembayaran->tahun_ajaran)
                ->whereIn('status', [SppPembayaran::STATUS_BELUM, SppPembayaran::STATUS_DITOLAK])
                ->where('uuid', '!=', $pembayaran->uuid)
                ->orderBy('bulan')
                ->get();
        }

        return view('keuangan.tagihan.show', [
            'pembayaran' => $pembayaran->loadMissing('verifikator'),
            'siswa'      => $siswa,
            'banks'      => KeuanganBank::active($siswa->va ?: '-'),
            'va'         => $siswa->va,
            'payable'    => $payable,
            'lainnya'    => $lainnya,
        ]);
    }

    public function upload(Request $request, SppPembayaran $pembayaran)
    {
        $siswa = $this->guard($pembayaran);

        if ($pembayaran->status === SppPembayaran::STATUS_LUNAS) {
            return back()->with('error', 'Tagihan ini sudah lunas.');
        }

        $request->validate([
            'bank'  => 'required|string|max:60',
            'bukti' => 'required|image|mimes:jpeg,jpg,png,webp|max:4096',
            'tanggal_bayar' => 'nullable|date',
            'bulan_lain'    => 'nullable|array',
            'bulan_lain.*'  => 'string',
        ], [
            'bukti.required' => 'Bukti pembayaran wajib diunggah.',
            'bukti.image'    => 'Bukti harus berupa gambar.',
            'bukti.max'      => 'Ukuran gambar maksimal 4 MB.',
        ]);

        // Target = bulan ini + bulan lain yang dicentang (validasi milik siswa & belum lunas).
        $targets = collect([$pembayaran]);
        if ($request->filled('bulan_lain')) {
            $extra = SppPembayaran::whereIn('uuid', (array) $request->input('bulan_lain'))
                ->where('id_siswa', $siswa->uuid)
                ->where('tahun_ajaran', $pembayaran->tahun_ajaran)
                ->whereIn('status', [SppPembayaran::STATUS_BELUM, SppPembayaran::STATUS_DITOLAK])
                ->get();
            $targets = $targets->concat($extra)->unique('uuid')->values();
        }

        $tanggal = $request->date('tanggal_bayar') ?? now();
        $bank    = (string) $request->string('bank');
        $file    = $request->file('bukti');
        $ext     = $file->getClientOriginalExtension();
        $dir     = 'bukti-spp/' . $siswa->uuid;
        // Satu kali bayar (boleh banyak bulan) ditandai satu batch_id agar
        // bendahara dapat memverifikasi sekaligus.
        $batchId = (string) Str::uuid();

        // Simpan file SEKALI, lalu salin satu salinan per bulan agar tiap baris
        // memiliki file sendiri (aman saat salah satu ditolak & diunggah ulang).
        $firstPath = null;
        foreach ($targets as $t) {
            if ($t->bukti_path) {
                Storage::disk('local')->delete($t->bukti_path);
            }
            $name = 'spp-' . $t->bulan . '-' . Str::random(8) . '.' . $ext;
            $path = $dir . '/' . $name;
            if ($firstPath === null) {
                $firstPath = $file->storeAs($dir, $name, 'local');
                $path = $firstPath;
            } else {
                Storage::disk('local')->copy($firstPath, $path);
            }
            $t->fill([
                'status'        => SppPembayaran::STATUS_MENUNGGU,
                'batch_id'      => $batchId,
                'bank'          => $bank,
                'bukti_path'    => $path,
                'tanggal_bayar' => $tanggal,
                'catatan'       => null,
                'diverifikasi_oleh' => null,
                'diverifikasi_pada' => null,
            ])->save();
        }

        $n   = $targets->count();
        $msg = $n > 1
            ? "Bukti pembayaran untuk {$n} bulan terkirim. Menunggu verifikasi bendahara."
            : 'Bukti pembayaran ' . $pembayaran->label_bulan . ' terkirim. Menunggu verifikasi bendahara.';

        return redirect()
            ->route('keuangan.tagihan.index', ['anak' => $siswa->uuid])
            ->with('success', $msg);
    }

    /**
     * Streaming file bukti dari disk PRIVAT. Diizinkan untuk bendahara/admin
     * (verifikasi) atau pemilik tagihan (siswa/ortu). Tidak dapat diakses tanpa auth.
     */
    public function buktiFile(SppPembayaran $pembayaran)
    {
        $isStaff = in_array(auth()->user()->access, ['superadmin', 'admin', 'bendahara'], true);
        if (! $isStaff) {
            $this->guard($pembayaran); // pemilik saja (siswa/ortu)
        }

        abort_if(
            ! $pembayaran->bukti_path || ! Storage::disk('local')->exists($pembayaran->bukti_path),
            Response::HTTP_NOT_FOUND
        );

        return Storage::disk('local')->response($pembayaran->bukti_path);
    }

    // ─────────────────────────── helper ───────────────────────────

    /**
     * Daftar siswa yang boleh diakses pengguna: dirinya (jika siswa) atau
     * anak-anaknya (jika orang tua).
     *
     * @return \Illuminate\Support\Collection<int, Siswa>
     */
    private function children()
    {
        $user = auth()->user();

        if ($user->siswa) {
            return collect([$user->siswa]);
        }

        if ($user->access === 'orangtua') {
            return Orangtua::where('id_login', $user->uuid)
                ->with('siswa.kelas')
                ->get()
                ->pluck('siswa')
                ->filter()
                ->values();
        }

        return collect();
    }

    private function resolveSiswa(Request $request, $children): Siswa
    {
        $anak = (string) $request->query('anak', '');
        return $children->firstWhere('uuid', $anak) ?? $children->first();
    }

    /** Pastikan pembayaran milik siswa pengguna; kembalikan siswa-nya. */
    private function guard(SppPembayaran $pembayaran): Siswa
    {
        $siswa = $this->children()->firstWhere('uuid', $pembayaran->id_siswa);
        abort_if(!$siswa, Response::HTTP_FORBIDDEN, 'Tagihan ini bukan milik Anda.');
        return $siswa;
    }
}
