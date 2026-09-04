<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use PDOException;
use Tests\TestCase;

/**
 * Penangan global (bootstrap/app.php) — jaring pengaman utk QueryException yg terjadi di
 * LUAR jangkauan retryOnDbBusy() per-controller, terutama kegagalan koneksi saat Laravel
 * meresolve model lewat route-model-binding (sebelum kode controller sempat jalan sama
 * sekali). Dites lewat route ad-hoc yg sengaja melempar QueryException, bukan lewat
 * kegagalan koneksi sungguhan (tak bisa disimulasikan realistis di SQLite test).
 */
class DbBusyExceptionHandlerTest extends TestCase
{
    private function daftarkanRouteError(\Throwable $e): void
    {
        Route::middleware('web')->get('/__test/db-busy-probe', function () use ($e) {
            throw $e;
        });
    }

    private function dbBusyException(): QueryException
    {
        return new QueryException(
            'mysql', 'select 1', [],
            new PDOException('SQLSTATE[HY000] [2002] Operation not permitted')
        );
    }

    public function test_request_biasa_dapat_halaman_ramah_bukan_exception_mentah(): void
    {
        $this->daftarkanRouteError($this->dbBusyException());

        $response = $this->get('/__test/db-busy-probe');

        $response->assertStatus(503);
        $response->assertSeeText('Server Sedang Sibuk');
        $response->assertDontSee('SQLSTATE', false);
    }

    public function test_request_json_dapat_pesan_ramah_terstruktur(): void
    {
        $this->daftarkanRouteError($this->dbBusyException());

        $response = $this->getJson('/__test/db-busy-probe');

        $response->assertStatus(503);
        $response->assertJson(['ok' => false]);
        $response->assertJsonStructure(['message']);
    }

    public function test_query_exception_lain_tetap_dilempar_apa_adanya(): void
    {
        $this->daftarkanRouteError(new QueryException(
            'mysql', 'select * from tabel_gak_ada', [],
            new PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'tabel_gak_ada' doesn't exist")
        ));

        $response = $this->get('/__test/db-busy-probe');

        // BUKAN 503 "server sibuk" (satu2nya status yg dipakai penangan custom kita) —
        // harus lolos ke penanganan error default Laravel (500). TIDAK assertDontSee
        // teks halaman custom di sini — mode debug lokal (APP_DEBUG=true) menampilkan
        // source code test ini SENDIRI di trace Ignition, yg kebetulan memuat literal
        // teks yg sama, jadi rawan false-positive.
        $response->assertStatus(500);
    }
}
