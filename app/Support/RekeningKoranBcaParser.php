<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Parser laporan transaksi Virtual Account BCA (format "R-5401" — laporan
 * H2H/e-banking untuk rekening perusahaan). Baris data punya kolom tetap:
 * NO, NO.PELANGGAN (suffix VA), NAMA PELANGGAN, NILAI TRANSAKSI, TGL.TXN,
 * WAKTU, LOKASI, KETERANGAN1, KETERANGAN2 — dipisah spasi variabel, bukan
 * lebar kolom tetap, jadi dicocokkan pakai regex per-baris (baris
 * header/subtotal/footer otomatis tak cocok pola ini, tak perlu difilter
 * manual).
 */
class RekeningKoranBcaParser
{
    private const PATTERN = '/^\s*\d+\s+(\d{6,})\s+.+?\s+IDR\s+([\d.,]+)\s+(\d{2}\/\d{2}\/\d{2})\s+(\d{2}:\d{2}:\d{2})\s+(\S+)/';

    /**
     * @return array<int, array{no_pelanggan:string, nominal:int, tanggal:Carbon, waktu:string, lokasi:string, baris_asli:string}>
     */
    public static function parse(string $content): array
    {
        $out = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            if (!preg_match(self::PATTERN, $line, $m)) {
                continue;
            }

            $nominal = (int) round((float) str_replace(',', '', $m[2]));
            if ($nominal <= 0) {
                continue;
            }

            try {
                $tanggal = Carbon::createFromFormat('d/m/y', $m[3])->startOfDay();
            } catch (\Throwable $e) {
                continue;
            }

            $out[] = [
                'no_pelanggan' => $m[1],
                'nominal'      => $nominal,
                'tanggal'      => $tanggal,
                'waktu'        => $m[4],
                'lokasi'       => $m[5],
                'baris_asli'   => trim($line),
            ];
        }

        return $out;
    }
}
