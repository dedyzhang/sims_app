<?php

namespace App\Support\RekeningKoran;

/**
 * Deteksi bank & parse rekening koran via strategy pattern (A4).
 */
class RekeningKoranParserResolver
{
    /**
     * @return array{
     *     bank: string,
     *     transaksi: array<int, array{no_pelanggan:string, nominal:int, tanggal:\Carbon\Carbon, waktu:string, lokasi:string, baris_asli:string}>,
     *     baris_gagal: int
     * }
     */
    public static function resolve(string $content): array
    {
        $parsers = config('keuangan-ai.parser_rekening_koran', [
            RekeningKoranBcaParser::class,
            RekeningKoranMandiriParser::class,
        ]);

        foreach ($parsers as $parserClass) {
            if (! is_subclass_of($parserClass, RekeningKoranParserInterface::class)) {
                continue;
            }
            if ($parserClass::detect($content)) {
                $transaksi = $parserClass::parse($content);

                return [
                    'bank'        => $parserClass::bankCode(),
                    'transaksi'   => $transaksi,
                    'baris_gagal' => 0,
                ];
            }
        }

        // Fallback: coba semua parser, ambil yg hasilnya terbanyak
        $best = ['bank' => 'Tidak dikenali', 'transaksi' => [], 'baris_gagal' => 0];
        foreach ($parsers as $parserClass) {
            if (! is_subclass_of($parserClass, RekeningKoranParserInterface::class)) {
                continue;
            }
            $rows = $parserClass::parse($content);
            if (count($rows) > count($best['transaksi'])) {
                $best = [
                    'bank'        => $parserClass::bankCode(),
                    'transaksi'   => $rows,
                    'baris_gagal' => 0,
                ];
            }
        }

        return $best;
    }
}
