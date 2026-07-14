<?php

namespace App\Support;

/**
 * Daftar agama baku — satu sumber kebenaran dipakai bareng oleh dropdown Excel
 * (SiswaTemplateExport/GuruTemplateExport) dan validasi saat impor (SiswaImport/GuruImport),
 * supaya keduanya tidak pernah berbeda daftar.
 */
class Agama
{
    public const LIST = ['Islam', 'Kristen Protestan', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];

    /** Formula data-validation Excel, mis. '"Islam,Kristen Protestan,Katolik,..."'. */
    public static function excelFormula(): string
    {
        return '"' . implode(',', self::LIST) . '"';
    }

    /** Cocokkan input bebas ke salah satu nilai baku (longgar soal spasi/huruf besar-kecil).
     *  Kembalikan null bila kosong ATAU tidak cocok satu pun — supaya data yang tersimpan
     *  selalu salah satu dari daftar baku, bukan teks bebas dari pengisi Excel. */
    public static function normalize(?string $val): ?string
    {
        $val = trim((string) $val);
        if ($val === '') {
            return null;
        }
        foreach (self::LIST as $baku) {
            if (strcasecmp($val, $baku) === 0) {
                return $baku;
            }
        }
        return null;
    }
}
