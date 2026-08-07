<?php

namespace App\Services\Keuangan;

use App\Models\SppPembayaran;
use App\Support\TahunAjaran;
use Illuminate\Support\Collection;

/**
 * Wawasan operasional bendahara (Fase C1) — metrik non-nominal, rule-based.
 *
 * Fokus pola waktu & antrian; bukan duplikat Narasi Data pimpinan dan tanpa agregat rupiah.
 */
class BendaharaWawasanService
{
    private const HARI_LABEL = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    /**
     * @return array{
     *     tahun_ajaran: string,
     *     antrian: array{menunggu:int, terverifikasi:int, ditolak:int},
     *     keterlambatan: array{total_lunas:int, terlambat:int, persen:float},
     *     hari_bayar: array<int, array{hari:string, jumlah:int}>,
     *     hari_terpopuler: string|null,
     *     bulan_menunggu_terbanyak: array{label:string, jumlah:int}|null,
     *     poin_narasi: list<string>
     * }
     */
    public function ringkasan(string $tahunAjaran): array
    {
        $base = SppPembayaran::where('tahun_ajaran', $tahunAjaran);

        $antrian = [
            'menunggu'      => (int) (clone $base)->where('status', SppPembayaran::STATUS_MENUNGGU)->count(),
            'terverifikasi' => (int) (clone $base)->where('status', SppPembayaran::STATUS_TERVERIFIKASI)->count(),
            'ditolak'       => (int) (clone $base)->where('status', SppPembayaran::STATUS_DITOLAK)->count(),
        ];

        $lunas = (clone $base)
            ->where('status', SppPembayaran::STATUS_LUNAS)
            ->whereNotNull('tanggal_bayar')
            ->get(['tanggal_bayar', 'jatuh_tempo', 'tahun_ajaran', 'bulan']);

        $terlambat = $lunas->filter(fn (SppPembayaran $p) => $this->isTerlambat($p))->count();
        $totalLunas = $lunas->count();
        $persenTerlambat = $totalLunas > 0 ? round($terlambat / $totalLunas * 100, 1) : 0.0;

        $hariBayar = $this->distribusiHariBayar($lunas);
        $hariTerpopuler = collect($hariBayar)->sortByDesc('jumlah')->first();
        $bulanMenunggu = $this->bulanMenungguTerbanyak($tahunAjaran);

        $poin = $this->buatPoinNarasi($antrian, $totalLunas, $terlambat, $persenTerlambat, $hariTerpopuler, $bulanMenunggu);

        return [
            'tahun_ajaran'            => $tahunAjaran,
            'antrian'                 => $antrian,
            'keterlambatan'           => [
                'total_lunas' => $totalLunas,
                'terlambat'   => $terlambat,
                'persen'      => $persenTerlambat,
            ],
            'hari_bayar'              => $hariBayar,
            'hari_terpopuler'         => $hariTerpopuler && $hariTerpopuler['jumlah'] > 0 ? $hariTerpopuler['hari'] : null,
            'bulan_menunggu_terbanyak'=> $bulanMenunggu,
            'poin_narasi'             => $poin,
        ];
    }

    /**
     * Teks prompt untuk AI — hanya metrik non-nominal (tanpa rupiah).
     */
    public function promptNarasi(array $ringkasan): string
    {
        $a = $ringkasan['antrian'];
        $k = $ringkasan['keterlambatan'];
        $hari = collect($ringkasan['hari_bayar'])
            ->map(fn ($h) => "{$h['hari']}: {$h['jumlah']} transaksi")
            ->implode(', ');
        $bulan = $ringkasan['bulan_menunggu_terbanyak']
            ? "{$ringkasan['bulan_menunggu_terbanyak']['label']} ({$ringkasan['bulan_menunggu_terbanyak']['jumlah']} menunggu)"
            : 'tidak ada data';

        return "Wawasan operasional SPP T.A. {$ringkasan['tahun_ajaran']} (internal bendahara, TANPA nominal rupiah):\n"
            ."- Antrian: {$a['menunggu']} menunggu verifikasi, {$a['terverifikasi']} menunggu validasi bank, {$a['ditolak']} ditolak.\n"
            ."- Dari {$k['total_lunas']} pembayaran lunas, {$k['terlambat']} ({$k['persen']}%) dibayar setelah jatuh tempo.\n"
            ."- Pola hari bayar (jumlah transaksi): {$hari}.\n"
            ."- Hari paling ramai: ".($ringkasan['hari_terpopuler'] ?? 'belum teridentifikasi').".\n"
            ."- Bulan dengan antrian menunggu terbanyak: {$bulan}.\n"
            .'Poin aturan sistem: '.implode(' ', $ringkasan['poin_narasi']);
    }

    private function isTerlambat(SppPembayaran $p): bool
    {
        if (! $p->tanggal_bayar) {
            return false;
        }

        $jatuh = $p->jatuh_tempo
            ?? TahunAjaran::tanggal($p->tahun_ajaran, (int) $p->bulan)->endOfMonth();

        return $p->tanggal_bayar->startOfDay()->gt($jatuh->startOfDay());
    }

    /**
     * @return list<array{hari:string, jumlah:int}>
     */
    private function distribusiHariBayar(Collection $lunas): array
    {
        $counts = array_fill(1, 7, 0);

        foreach ($lunas as $p) {
            $dow = (int) $p->tanggal_bayar->dayOfWeekIso;
            $counts[$dow]++;
        }

        $out = [];
        foreach (self::HARI_LABEL as $iso => $label) {
            $out[] = ['hari' => $label, 'jumlah' => $counts[$iso]];
        }

        return $out;
    }

    /**
     * @return array{label:string, jumlah:int}|null
     */
    private function bulanMenungguTerbanyak(string $tahunAjaran): ?array
    {
        $rows = SppPembayaran::where('tahun_ajaran', $tahunAjaran)
            ->where('status', SppPembayaran::STATUS_MENUNGGU)
            ->selectRaw('bulan, COUNT(*) as jumlah')
            ->groupBy('bulan')
            ->orderByDesc('jumlah')
            ->first();

        if (! $rows || (int) $rows->jumlah === 0) {
            return null;
        }

        $idx = (int) $rows->bulan;
        $label = TahunAjaran::BULAN[$idx][0] ?? "Bulan {$idx}";

        return ['label' => $label, 'jumlah' => (int) $rows->jumlah];
    }

    /**
     * @param  array{menunggu:int, terverifikasi:int, ditolak:int}  $antrian
     * @param  array{hari:string, jumlah:int}|null  $hariTerpopuler
     * @param  array{label:string, jumlah:int}|null  $bulanMenunggu
     * @return list<string>
     */
    private function buatPoinNarasi(
        array $antrian,
        int $totalLunas,
        int $terlambat,
        float $persenTerlambat,
        ?array $hariTerpopuler,
        ?array $bulanMenunggu,
    ): array {
        $poin = [];

        if ($antrian['menunggu'] >= config('keuangan-ai.digest.menunggu_min', 10)) {
            $poin[] = "Antrian verifikasi menumpuk ({$antrian['menunggu']} bukti menunggu).";
        }

        if ($antrian['terverifikasi'] > 0) {
            $poin[] = "{$antrian['terverifikasi']} tagihan sudah diverifikasi dan menunggu cocokkan rekening koran.";
        }

        if ($totalLunas > 0 && $persenTerlambat >= 20) {
            $poin[] = "Sekitar {$persenTerlambat}% pembayaran lunas masuk setelah jatuh tempo — pertimbangkan pengingat lebih awal.";
        } elseif ($totalLunas > 0 && $terlambat === 0) {
            $poin[] = 'Semua pembayaran lunas tercatat tepat waktu atau sebelum jatuh tempo.';
        }

        if ($hariTerpopuler && $hariTerpopuler['jumlah'] > 0) {
            $poin[] = "Pola pembayaran paling ramai di hari {$hariTerpopuler['hari']}.";
        }

        if ($bulanMenunggu) {
            $poin[] = "Bulan {$bulanMenunggu['label']} punya antrian menunggu terbanyak ({$bulanMenunggu['jumlah']} bukti).";
        }

        if ($antrian['ditolak'] > 0) {
            $poin[] = "{$antrian['ditolak']} pengajuan ditolak — cek ulang bukti atau komunikasi ke wali.";
        }

        if ($poin === []) {
            $poin[] = 'Belum ada pola signifikan; lanjutkan pemantauan rutin antrian verifikasi.';
        }

        return $poin;
    }
}
