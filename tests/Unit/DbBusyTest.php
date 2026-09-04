<?php

namespace Tests\Unit;

use App\Support\DbBusy;
use Illuminate\Database\QueryException;
use PDOException;
use Tests\TestCase;

/**
 * DbBusy::terdeteksi() HARUS spesifik ke SATU jenis error (penolakan koneksi sesaat krn
 * max_user_connections hosting shared) — TIDAK boleh menangkap QueryException lain secara
 * umum, supaya bug SQL sungguhan tetap tampil apa adanya, tak ketutup pesan "server sibuk".
 */
class DbBusyTest extends TestCase
{
    public function test_mendeteksi_operation_not_permitted(): void
    {
        $e = new QueryException(
            'mysql', 'select 1', [],
            new PDOException('SQLSTATE[HY000] [2002] Operation not permitted')
        );

        $this->assertTrue(DbBusy::terdeteksi($e));
    }

    public function test_tidak_mendeteksi_query_exception_lain(): void
    {
        $e = new QueryException(
            'mysql', 'select * from tabel_gak_ada', [],
            new PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'tabel_gak_ada' doesn't exist")
        );

        $this->assertFalse(DbBusy::terdeteksi($e));
    }

    public function test_tidak_mendeteksi_exception_biasa(): void
    {
        $this->assertFalse(DbBusy::terdeteksi(new \RuntimeException('Sesuatu yang lain rusak.')));
    }
}
