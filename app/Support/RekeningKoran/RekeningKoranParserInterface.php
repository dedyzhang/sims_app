<?php

namespace App\Support\RekeningKoran;

/**
 * Kontrak parser rekening koran / laporan transaksi VA per bank.
 *
 * @return array<int, array{no_pelanggan:string, nominal:int, tanggal:\Carbon\Carbon, waktu:string, lokasi:string, baris_asli:string}>
 */
interface RekeningKoranParserInterface
{
    /** Kode bank singkat (mis. BCA, Mandiri). */
    public static function bankCode(): string;

    /** Deteksi apakah konten file cocok format bank ini. */
    public static function detect(string $content): bool;

    /** Parse konten file menjadi baris transaksi normalisasi. */
    public static function parse(string $content): array;
}
