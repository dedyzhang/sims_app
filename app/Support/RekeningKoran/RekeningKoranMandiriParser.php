<?php

namespace App\Support\RekeningKoran;

use Carbon\Carbon;

/**
 * Parser laporan transaksi VA Mandiri (format CSV/TSV atau fixed-width sederhana).
 *
 * Format CSV yang didukung (header opsional):
 *   No Pelanggan,Nama,Nilai,Tanggal,Waktu
 * atau baris data:
 *   402353;BRYAN DOMINIC;770000;20/06/2026;06:51:52
 *
 * Format fixed-width (mirip BCA tanpa header R-5401):
 *   1  402353  BRYAN DOMINIC  IDR 770,000.00  20/06/26  06:51:52  9503N
 */
class RekeningKoranMandiriParser implements RekeningKoranParserInterface
{
    private const FIXED_PATTERN = '/^\s*\d+\s+(\d{6,})\s+.+?\s+IDR\s+([\d.,]+)\s+(\d{2}\/\d{2}\/\d{2,4})\s+(\d{2}:\d{2}:\d{2})\s+(\S+)/';

    public static function bankCode(): string
    {
        return 'Mandiri';
    }

    public static function detect(string $content): bool
    {
        if (str_contains($content, 'MANDIRI') || str_contains($content, 'LAPORAN TRANSAKSI VA MANDIRI')) {
            return true;
        }

        // CSV dengan delimiter ; atau , dan minimal satu baris data VA
        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with(strtolower($line), 'no')) {
                continue;
            }
            if (preg_match('/^(\d{6,})[;,](.+?)[;,]([\d.,]+)[;,](\d{2}\/\d{2}\/\d{2,4})/', $line)) {
                return true;
            }
        }

        return false;
    }

    public static function parse(string $content): array
    {
        $out = [];
        $errors = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) as $lineNum => $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^(no\.?|sub total|total|===)/i', $line)) {
                continue;
            }

            $parsed = self::parseLine($line);
            if ($parsed === null) {
                if (strlen($line) > 10) {
                    $errors[] = ['baris' => $lineNum + 1, 'pesan' => 'Format tidak dikenali'];
                }
                continue;
            }

            $out[] = $parsed;
        }

        return $out;
    }

    /**
     * @return array{no_pelanggan:string, nominal:int, tanggal:Carbon, waktu:string, lokasi:string, baris_asli:string}|null
     */
    private static function parseLine(string $line): ?array
    {
        if (preg_match(self::FIXED_PATTERN, $line, $m)) {
            return self::buildRow($m[1], $m[2], $m[3], $m[4], $m[5], $line);
        }

        // CSV/TSV: no_pelanggan;nama;nominal;tanggal;waktu
        $parts = preg_split('/[;,|\t]/', $line);
        if (count($parts) >= 4 && preg_match('/^\d{6,}$/', trim($parts[0]))) {
            $noPelanggan = trim($parts[0]);
            $nominalRaw  = trim($parts[2] ?? $parts[1]);
            $tanggalRaw  = trim($parts[3] ?? '');
            $waktu       = trim($parts[4] ?? '00:00:00');

            $nominal = (int) preg_replace('/\D/', '', $nominalRaw);
            if ($nominal <= 0) {
                return null;
            }

            return self::buildRow($noPelanggan, (string) $nominal, $tanggalRaw, $waktu, '-', $line);
        }

        return null;
    }

    private static function buildRow(string $noPelanggan, string $nominalRaw, string $tanggalRaw, string $waktu, string $lokasi, string $line): ?array
    {
        $nominal = (int) round((float) str_replace(',', '', $nominalRaw));
        if ($nominal <= 0 && ! ctype_digit(preg_replace('/\D/', '', $nominalRaw))) {
            return null;
        }
        if ($nominal <= 0) {
            $nominal = (int) preg_replace('/\D/', '', $nominalRaw);
        }
        if ($nominal <= 0) {
            return null;
        }

        $tanggal = self::parseDate($tanggalRaw);
        if (! $tanggal) {
            return null;
        }

        return [
            'no_pelanggan' => $noPelanggan,
            'nominal'      => $nominal,
            'tanggal'      => $tanggal,
            'waktu'        => $waktu,
            'lokasi'       => $lokasi,
            'baris_asli'   => trim($line),
        ];
    }

    private static function parseDate(string $raw): ?Carbon
    {
        foreach (['d/m/y', 'd/m/Y', 'Y-m-d', 'd-m-Y'] as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, trim($raw))->startOfDay();
            } catch (\Throwable) {
                // coba format berikutnya
            }
        }

        return null;
    }
}
