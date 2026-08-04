<?php

namespace Tests\Feature;

use App\Models\UserFeedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `php artisan feedback:diagnose` — dibuat karena kegagalan kirim email Masukan
 * SELALU silent (lihat FeedbackController::notifyDevelopmentTeam): job antrean yg
 * gagal masuk nyaris tak pernah throw, dan kegagalan SMTP di dalam worker tak pernah
 * sampai ke log controller. Command ini WAJIB betul2 mengirim (lewat --kirim-tes,
 * dipaksa sinkron) supaya error transport sungguhan kelihatan, dan baris tes yg
 * dibuat WAJIB tak nyangkut di tabel setelah selesai (dites eksplisit di bawah).
 */
class DiagnoseFeedbackMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_gagal_jelas_saat_alamat_tujuan_kosong(): void
    {
        config(['feedback.development_email' => '']);

        $this->artisan('feedback:diagnose')
            ->assertExitCode(1)
            ->expectsOutputToContain('Tidak ada alamat tujuan sama sekali');
    }

    public function test_tanpa_kirim_tes_tidak_menyentuh_tabel_feedback(): void
    {
        config(['feedback.development_email' => 'admin@example.com', 'mail.default' => 'array', 'queue.default' => 'sync']);

        $before = UserFeedback::count();

        $this->artisan('feedback:diagnose')->assertExitCode(0);

        $this->assertSame($before, UserFeedback::count());
    }

    public function test_kirim_tes_berhasil_dan_baris_diagnostik_dibersihkan(): void
    {
        config([
            'feedback.development_email' => 'admin@example.com',
            'mail.default' => 'array', // transport aman utk test — tak menyentuh jaringan sungguhan
        ]);

        $this->artisan('feedback:diagnose', ['--kirim-tes' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('Terkirim TANPA exception');

        // Baris diagnostik yg dibuat utk tes kirim TIDAK boleh nyangkut di menu Masukan admin.
        $this->assertDatabaseMissing('user_feedback', ['subject' => '[Diagnostik] Tes email feedback']);
    }

    public function test_kirim_tes_membersihkan_baris_diagnostik_walau_pengiriman_gagal(): void
    {
        config([
            'feedback.development_email' => 'admin@example.com',
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1, // port tak akan pernah menerima koneksi -> transport gagal cepat
            'mail.mailers.smtp.timeout' => 1,
        ]);

        $this->artisan('feedback:diagnose', ['--kirim-tes' => true])
            ->assertExitCode(1)
            ->expectsOutputToContain('GAGAL saat mengirim');

        $this->assertDatabaseMissing('user_feedback', ['subject' => '[Diagnostik] Tes email feedback']);
    }

    public function test_melaporkan_job_menumpuk_di_tabel_jobs(): void
    {
        config(['feedback.development_email' => 'admin@example.com', 'queue.default' => 'database']);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => 'dummy',
            'attempts' => 0,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->artisan('feedback:diagnose')
            ->assertExitCode(0)
            ->expectsOutputToContain('Job menumpuk di tabel \'jobs\' saat ini: 1')
            ->expectsOutputToContain('worker TIDAK berjalan');
    }
}
