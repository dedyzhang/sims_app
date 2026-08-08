<?php

namespace App\Services\Keuangan;

use App\Models\SppPembayaran;
use Illuminate\Support\Collection;

/**
 * Deteksi duplikat bukti & anomali nominal (Fase B2) — flag saja, tidak memblokir.
 */
class SppAnomalyDetector
{
    public const FLAG_DUPLIKAT_BUKTI = 'duplikat_bukti';
    public const FLAG_NOMINAL_JANGGAL = 'nominal_janggal';
    public const FLAG_PENGAJUAN_GANDA = 'pengajuan_ganda';

    /**
     * @return Collection<int, array{pembayaran:SppPembayaran, flags:array<int, array{kode:string, label:string, tingkat:string}>}>
     */
    public function scan(?string $tahunAjaran = null): Collection
    {
        $query = SppPembayaran::with('siswa.kelas')
            ->whereIn('status', [
                SppPembayaran::STATUS_MENUNGGU,
                SppPembayaran::STATUS_TERVERIFIKASI,
            ]);

        if ($tahunAjaran) {
            $query->where('tahun_ajaran', $tahunAjaran);
        }

        $rows = $query->get();

        $buktiIndex = $this->indexBuktiPath($rows);
        $gandaIndex = $this->indexPengajuanGanda($rows);

        return $rows
            ->map(function (SppPembayaran $p) use ($buktiIndex, $gandaIndex) {
                $flags = [];

                if ($p->bukti_path && ($buktiIndex[$p->bukti_path] ?? 0) > 1) {
                    $flags[] = [
                        'kode'    => self::FLAG_DUPLIKAT_BUKTI,
                        'label'   => 'Bukti transfer sama dipakai di lebih dari satu tagihan',
                        'tingkat' => 'tinggi',
                    ];
                }

                $tarif = (int) preg_replace('/\D/', '', (string) ($p->siswa?->spp ?? ''));
                if ($tarif > 0 && (int) $p->nominal !== $tarif) {
                    $flags[] = [
                        'kode'    => self::FLAG_NOMINAL_JANGGAL,
                        'label'   => 'Nominal tagihan (Rp '.number_format($p->nominal, 0, ',', '.').') ≠ tarif siswa (Rp '.number_format($tarif, 0, ',', '.').')',
                        'tingkat' => 'sedang',
                    ];
                }

                $gandaKey = $this->pengajuanKey($p);
                if (isset($gandaIndex[$gandaKey]) && $gandaIndex[$gandaKey] > 1) {
                    $flags[] = [
                        'kode'    => self::FLAG_PENGAJUAN_GANDA,
                        'label'   => 'Ada pengajuan ganda untuk siswa/bulan/nominal yang sama',
                        'tingkat' => 'sedang',
                    ];
                }

                return [
                    'pembayaran' => $p,
                    'flags'      => $flags,
                ];
            })
            ->filter(fn ($item) => ! empty($item['flags']))
            ->sortByDesc(fn ($item) => collect($item['flags'])->contains(fn ($f) => $f['tingkat'] === 'tinggi'))
            ->values();
    }

    /**
     * @return array<string, int>
     */
    public function flagsFor(SppPembayaran $pembayaran): array
    {
        $item = $this->scan($pembayaran->tahun_ajaran)
            ->first(fn ($row) => $row['pembayaran']->uuid === $pembayaran->uuid);

        return $item['flags'] ?? [];
    }

    /** @param Collection<int, SppPembayaran> $rows */
    private function indexBuktiPath(Collection $rows): array
    {
        $index = [];
        foreach ($rows as $p) {
            if ($p->bukti_path) {
                $index[$p->bukti_path] = ($index[$p->bukti_path] ?? 0) + 1;
            }
        }

        return $index;
    }

    /** @param Collection<int, SppPembayaran> $rows */
    private function indexPengajuanGanda(Collection $rows): array
    {
        $index = [];
        foreach ($rows as $p) {
            $key = $this->pengajuanKey($p);
            $index[$key] = ($index[$key] ?? 0) + 1;
        }

        return $index;
    }

    private function pengajuanKey(SppPembayaran $p): string
    {
        return implode('|', [
            $p->id_siswa,
            $p->tahun_ajaran,
            (string) $p->bulan,
            (string) $p->nominal,
            (string) optional($p->tanggal_bayar)->toDateString(),
        ]);
    }
}
