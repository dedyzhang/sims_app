<?php

namespace App\Sarpras\Support;

/*
|--------------------------------------------------------------------------
| Helper UANG — semua perhitungan rupiah pakai BCMath (integer string).
|--------------------------------------------------------------------------
| ATURAN: nilai uang DISIMPAN sebagai integer rupiah (bukan float).
| Semua aritmetika lewat BCMath agar tidak ada galat pembulatan float.
*/
class Rupiah
{
    /** Penjumlahan aman (BCMath), kembalikan integer string. */
    public static function add(int|string $a, int|string $b): string
    {
        return bcadd((string) $a, (string) $b, 0);
    }

    /** Pengurangan aman (BCMath). */
    public static function sub(int|string $a, int|string $b): string
    {
        return bcsub((string) $a, (string) $b, 0);
    }

    /** Perkalian aman (BCMath) — mis. harga × qty. */
    public static function mul(int|string $a, int|string $b): string
    {
        return bcmul((string) $a, (string) $b, 0);
    }

    /**
     * Total dari daftar item: sum(harga * qty).
     * @param iterable<array{harga:int|string, qty:int|string}> $items
     */
    public static function sumItems(iterable $items): string
    {
        $total = '0';
        foreach ($items as $item) {
            $sub = static::mul($item['harga'] ?? 0, $item['qty'] ?? 0);
            $total = static::add($total, $sub);
        }

        return $total;
    }

    /** Format tampilan: 1500000 -> "Rp 1.500.000". */
    public static function format(int|string|null $value): string
    {
        $value = (int) ($value ?? 0);

        return 'Rp ' . number_format($value, 0, ',', '.');
    }
}
