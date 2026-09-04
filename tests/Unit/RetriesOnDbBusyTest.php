<?php

namespace Tests\Unit;

use App\Http\Controllers\Concerns\RetriesOnDbBusy;
use Illuminate\Database\QueryException;
use PDOException;
use Tests\TestCase;

/**
 * Trait dipakai UjianSiswaController & UjianRuanganScanController — titik paling rawan
 * tembakan bersamaan saat ujian dimulai. Test ini pakai host anonim (bukan hit HTTP
 * sungguhan) supaya fokus murni ke PERILAKU retry-nya: coba lagi diam2 pada error
 * "server sibuk", TAPI langsung lempar error lain apa adanya (tak boleh ikut ditelan).
 */
class RetriesOnDbBusyTest extends TestCase
{
    private function host(): object
    {
        return new class {
            use RetriesOnDbBusy;

            public function coba(callable $fn)
            {
                return $this->retryOnDbBusy($fn, 3);
            }
        };
    }

    private function dbBusyException(): QueryException
    {
        return new QueryException(
            'mysql', 'select 1', [],
            new PDOException('SQLSTATE[HY000] [2002] Operation not permitted')
        );
    }

    public function test_berhasil_di_percobaan_kedua_setelah_gagal_sekali(): void
    {
        $percobaan = 0;

        $hasil = $this->host()->coba(function () use (&$percobaan) {
            $percobaan++;
            if ($percobaan < 2) {
                throw $this->dbBusyException();
            }

            return 'sukses';
        });

        $this->assertSame('sukses', $hasil);
        $this->assertSame(2, $percobaan);
    }

    public function test_menyerah_setelah_habis_jatah_percobaan(): void
    {
        $percobaan = 0;

        $this->expectException(QueryException::class);

        try {
            $this->host()->coba(function () use (&$percobaan) {
                $percobaan++;
                throw $this->dbBusyException();
            });
        } finally {
            $this->assertSame(3, $percobaan, 'harus tepat 3x percobaan sebelum menyerah');
        }
    }

    public function test_exception_lain_langsung_dilempar_tanpa_diulang(): void
    {
        $percobaan = 0;

        $this->expectException(\RuntimeException::class);

        try {
            $this->host()->coba(function () use (&$percobaan) {
                $percobaan++;
                throw new \RuntimeException('Bug sungguhan, bukan server sibuk.');
            });
        } finally {
            $this->assertSame(1, $percobaan, 'exception non-DbBusy tak boleh ikut diulang');
        }
    }
}
