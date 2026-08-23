<?php

namespace Tests\Feature;

use App\Exports\Keuangan\Rkas\RkasWorksheetSheet;
use App\Models\RkasPlan;
use App\Models\RkasReferenceSet;
use App\Models\User;
use App\Services\Keuangan\RkasValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RkasCompanionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $access, string $username): User
    {
        return User::create([
            'username' => $username,
            'password' => Hash::make('password'),
            'access' => $access,
        ]);
    }

    private function makeReferenceSet(array $rules = []): array
    {
        $set = RkasReferenceSet::create([
            'label' => 'Test BOSP',
            'tahun_anggaran' => 2026,
            'versi' => 'TEST-2026-'.bin2hex(random_bytes(2)),
            'jenjang' => 'Dikdasmen',
            'sumber_dana' => 'BOSP Reguler',
            'rules' => $rules,
            'is_active' => true,
        ]);
        $book = $set->references()->create([
            'kode_kegiatan' => '05.02.02',
            'komponen' => 'Buku',
            'uraian_kegiatan' => 'Buku teks',
        ]);
        $honor = $set->references()->create([
            'kode_kegiatan' => '07.12.01',
            'komponen' => 'Honor',
            'uraian_kegiatan' => 'Pembayaran honor',
        ]);

        return [$set, $book, $honor];
    }

    private function payload(RkasReferenceSet $set, string $referenceUuid, int $pagu = 1000): array
    {
        return [
            'npsn' => '12345678',
            'nama_sekolah' => 'Sekolah Test',
            'tahun_anggaran' => 2026,
            'jenjang' => 'Dikdasmen',
            'sumber_dana' => 'BOSP Reguler',
            'reference_set_uuid' => $set->uuid,
            'pagu' => $pagu,
            'items' => [[
                'reference_uuid' => $referenceUuid,
                'penjelasan_implementasi' => 'Pelaksanaan kegiatan sesuai rencana',
                'uraian_belanja' => 'Pengadaan bahan kegiatan',
                'bulan_dianggarkan' => 2,
                'jumlah' => 2,
                'satuan' => 'paket',
                'harga_satuan' => 100,
            ]],
        ];
    }

    public function test_bendahara_membuat_rkas_dengan_total_server_side_dan_tetap_draft(): void
    {
        $user = $this->makeUser('bendahara', 'bendahara_rkas_1');
        [$set, $book] = $this->makeReferenceSet();

        $response = $this->actingAs($user)->post('/keuangan/rkas', $this->payload($set, $book->uuid));

        $plan = RkasPlan::firstOrFail();
        $response->assertRedirect(route('keuangan.rkas.show', $plan));
        $this->assertSame(RkasPlan::STATUS_DRAFT, $plan->status);
        $this->assertDatabaseHas('rkas_items', ['plan_uuid' => $plan->uuid, 'total' => 200]);
    }

    public function test_kode_dari_reference_set_lain_ditolak(): void
    {
        $user = $this->makeUser('bendahara', 'bendahara_rkas_2');
        [$set] = $this->makeReferenceSet();
        [, $otherBook] = $this->makeReferenceSet();

        $response = $this->actingAs($user)->post('/keuangan/rkas', $this->payload($set, $otherBook->uuid));

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('rkas_plans', 0);
    }

    public function test_validasi_menandai_buku_minimum_dan_honor_maksimum(): void
    {
        $user = $this->makeUser('bendahara', 'bendahara_rkas_3');
        [$set, $book, $honor] = $this->makeReferenceSet([
            'percentages' => [
                ['label' => 'Buku', 'components' => ['Buku'], 'min_bps' => 1000],
                ['label' => 'Honor', 'components' => ['Honor'], 'max_bps' => 2000],
            ],
        ]);

        $payload = $this->payload($set, $book->uuid, 1000);
        $payload['items'][0]['jumlah'] = 1;
        $payload['items'][0]['harga_satuan'] = 50;
        $payload['items'][] = [
            'reference_uuid' => $honor->uuid,
            'penjelasan_implementasi' => 'Honor kegiatan',
            'uraian_belanja' => 'Honor narasumber kegiatan',
            'bulan_dianggarkan' => 3,
            'jumlah' => 3,
            'satuan' => 'orang',
            'harga_satuan' => 100,
        ];

        $this->actingAs($user)->post('/keuangan/rkas', $payload);
        $plan = RkasPlan::firstOrFail();
        $this->actingAs($user)->post(route('keuangan.rkas.validate', $plan))->assertRedirect();

        $this->assertDatabaseHas('rkas_validations', ['plan_uuid' => $plan->uuid, 'kode' => 'component_minimum', 'severity' => 'error']);
        $this->assertDatabaseHas('rkas_validations', ['plan_uuid' => $plan->uuid, 'kode' => 'component_maximum', 'severity' => 'error']);
        $this->assertSame(RkasPlan::STATUS_DRAFT, $plan->fresh()->status);
    }

    public function test_total_rkas_menolak_overflow_bigint(): void
    {
        $this->expectException(\OverflowException::class);

        app(RkasValidationService::class)->calculateTotal(PHP_INT_MAX, 2);
    }

    public function test_validasi_menolak_scope_referensi_yang_berbeda(): void
    {
        $user = $this->makeUser('bendahara', 'bendahara_rkas_scope');
        [$set, $book] = $this->makeReferenceSet();
        $payload = $this->payload($set, $book->uuid);
        $payload['jenjang'] = 'PAUD';

        $this->actingAs($user)->post('/keuangan/rkas', $payload);
        $plan = RkasPlan::firstOrFail();
        $this->actingAs($user)->post(route('keuangan.rkas.validate', $plan));

        $this->assertDatabaseHas('rkas_validations', ['plan_uuid' => $plan->uuid, 'kode' => 'reference_scope_mismatch', 'severity' => 'error']);
    }

    public function test_kepala_bisa_review_dan_guru_tidak_bisa_membuka_rkas(): void
    {
        [$set, $book] = $this->makeReferenceSet();
        $bendahara = $this->makeUser('bendahara', 'bendahara_rkas_4');
        $this->actingAs($bendahara)->post('/keuangan/rkas', $this->payload($set, $book->uuid));
        $plan = RkasPlan::firstOrFail();

        $this->actingAs($this->makeUser('kepala', 'kepala_rkas'))->get(route('keuangan.rkas.show', $plan))->assertOk();
        $this->actingAs($this->makeUser('guru', 'guru_rkas'))->get(route('keuangan.rkas.show', $plan))->assertForbidden();
    }

    public function test_admin_dapat_mengimpor_registry_referensi_dengan_checksum(): void
    {
        $admin = $this->makeUser('admin', 'admin_rkas_import');
        $file = UploadedFile::fake()->createWithContent(
            'referensi.csv',
            "kode_kegiatan,uraian_kegiatan,komponen\n09.01.01,Kegiatan baru,Buku\n"
        );

        $this->actingAs($admin)->post(route('keuangan.rkas.reference.import'), [
            'label' => 'Import Test',
            'tahun_anggaran' => 2026,
            'versi' => 'IMPORT-TEST',
            'jenjang' => 'Dikdasmen',
            'sumber_dana' => 'BOSP Reguler',
            'source_url' => 'https://example.test/reference',
            'rules_json' => '{"percentages":[]}',
            'file' => $file,
        ])->assertRedirect();

        $set = RkasReferenceSet::where('versi', 'IMPORT-TEST')->firstOrFail();
        $this->assertNotEmpty($set->source_checksum);
        $this->assertDatabaseHas('rkas_references', ['reference_set_uuid' => $set->uuid, 'kode_kegiatan' => '09.01.01']);
    }

    public function test_import_registry_referensi_menolak_kode_duplikat_sebagai_validasi(): void
    {
        $admin = $this->makeUser('admin', 'admin_rkas_import_duplicate');
        $file = UploadedFile::fake()->createWithContent(
            'referensi-duplikat.csv',
            "kode_kegiatan,uraian_kegiatan,komponen\n09.01.01,Kegiatan baru,Buku\n09.01.01,Kegiatan duplikat,Buku\n"
        );

        $this->actingAs($admin)->post(route('keuangan.rkas.reference.import'), [
            'label' => 'Import Duplikat',
            'tahun_anggaran' => 2026,
            'versi' => 'IMPORT-DUP',
            'jenjang' => 'Dikdasmen',
            'sumber_dana' => 'BOSP Reguler',
            'file' => $file,
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseMissing('rkas_reference_sets', ['versi' => 'IMPORT-DUP']);
    }

    public function test_index_rkas_memiliki_empty_state_yang_mengarahkan_setup(): void
    {
        $admin = $this->makeUser('admin', 'admin_rkas_empty_ui');

        $this->actingAs($admin)->get(route('keuangan.rkas.index'))
            ->assertOk()
            ->assertSee('Belum ada rencana RKAS')
            ->assertSee('Impor registry referensi sebelum membuat RKAS')
            ->assertSee('Batas integrasi resmi');
    }

    public function test_status_submitted_membuat_audit_manual_dan_tidak_menyatakan_sinkron_otomatis(): void
    {
        Storage::fake('local');
        $user = $this->makeUser('bendahara', 'bendahara_rkas_5');
        [$set, $book] = $this->makeReferenceSet();
        $this->actingAs($user)->post('/keuangan/rkas', $this->payload($set, $book->uuid));
        $plan = RkasPlan::firstOrFail();
        $this->actingAs($user)->post(route('keuangan.rkas.validate', $plan));
        $this->actingAs($user)->post(route('keuangan.rkas.status', $plan), [
            'status' => RkasPlan::STATUS_SUBMITTED,
            'note' => 'Dimasukkan manual ke ARKAS untuk verifikasi',
        ])->assertRedirect();

        $this->assertDatabaseHas('rkas_sync_logs', ['plan_uuid' => $plan->uuid, 'status' => RkasPlan::STATUS_SUBMITTED]);
        $this->assertSame(RkasPlan::STATUS_SUBMITTED, $plan->fresh()->status);
    }

    public function test_rkas_tervalidasi_dapat_diekspor_excel_dan_pdf(): void
    {
        $user = $this->makeUser('bendahara', 'bendahara_rkas_export');
        [$set, $book] = $this->makeReferenceSet();
        $this->actingAs($user)->post('/keuangan/rkas', $this->payload($set, $book->uuid));
        $plan = RkasPlan::firstOrFail();
        $this->actingAs($user)->post(route('keuangan.rkas.validate', $plan));

        $this->actingAs($user)->get(route('keuangan.rkas.export.excel', $plan))
            ->assertOk()
            ->assertDownload();
        $this->assertSame(RkasPlan::STATUS_VALIDATED, $plan->fresh()->status);

        $this->actingAs($user)->get(route('keuangan.rkas.export.pdf', $plan))
            ->assertOk()
            ->assertDownload();
    }

    public function test_export_rkas_mengamankan_teks_dari_formula_spreadsheet(): void
    {
        $user = $this->makeUser('bendahara', 'bendahara_rkas_formula');
        [$set, $book] = $this->makeReferenceSet();
        $payload = $this->payload($set, $book->uuid);
        $payload['items'][0]['penjelasan_implementasi'] = '=HYPERLINK("https://evil.test")';
        $payload['items'][0]['uraian_belanja'] = '@SUM(1+1)';

        $this->actingAs($user)->post('/keuangan/rkas', $payload);
        $item = RkasPlan::firstOrFail()->items()->with('reference')->firstOrFail();

        $row = (new RkasWorksheetSheet(RkasPlan::firstOrFail()))->map($item);

        $this->assertSame("'=HYPERLINK(\"https://evil.test\")", $row[4]);
        $this->assertSame("'@SUM(1+1)", $row[5]);
    }
}
