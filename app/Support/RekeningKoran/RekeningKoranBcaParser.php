<?php

namespace App\Support\RekeningKoran;

use Carbon\Carbon;

/**
 * Parser laporan transaksi Virtual Account BCA (format "R-5401").
 */
class RekeningKoranBcaParser implements RekeningKoranParserInterface
{
    private const PATTERN = '/^\s*\d+\s+(\d{6,})\s+.+?\s+IDR\s+([\d.,]+)\s+(\d{2}\/\d{2}\/\d{2})\s+(\d{2}:\d{2}:\d{2})\s+(\S+)/';

    public static function bankCode(): string
    {
        return 'BCA';
    }

    public static function detect(string $content): bool
    {
        return str_contains($content, 'R-5401') || str_contains($content, 'LAPORAN TRANSAKSI VIA E-BANKING');
    }

    public static function parse(string $content): array
    {
        $out = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            if (! preg_match(self::PATTERN, $line, $m)) {
                continue;
            }

            $nominal = (int) round((float) str_replace(',', '', $m[2]));
            if ($nominal <= 0) {
                continue;
            }

            try {
                $tanggal = Carbon::createFromFormat('d/m/y', $m[3])->startOfDay();
            } catch (\Throwable) {
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
