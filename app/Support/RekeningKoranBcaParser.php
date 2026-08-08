<?php

namespace App\Support;

use App\Support\RekeningKoran\RekeningKoranBcaParser as BcaParser;

/**
 * @deprecated Gunakan App\Support\RekeningKoran\RekeningKoranBcaParser langsung.
 *             Alias backward-compat untuk kode existing.
 */
class RekeningKoranBcaParser
{
    /**
     * @return array<int, array{no_pelanggan:string, nominal:int, tanggal:\Carbon\Carbon, waktu:string, lokasi:string, baris_asli:string}>
     */
    public static function parse(string $content): array
    {
        return BcaParser::parse($content);
    }
}
