<?php

namespace App\Console\Commands;

use App\Models\UserFeedback;
use App\Notifications\FeedbackSubmittedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Diagnosa kenapa email notifikasi Masukan (feedback) tidak sampai ke inbox admin,
 * walau .env sudah "kelihatan benar". Semua kegagalan di alur ini normalnya SILENT
 * (lihat FeedbackController::notifyDevelopmentTeam — job gagal masuk antrean nyaris
 * tak pernah throw, dan kegagalan transport SMTP yg terjadi di dalam queue worker
 * tak pernah sampai ke log controller sama sekali) — command ini membocorkan exception
 * ASLI dengan mengirim tes SINKRON (bypass antrean) supaya penyebabnya ketahuan
 * tanpa perlu nunggu worker atau baca tabel manual.
 */
class DiagnoseFeedbackMail extends Command
{
    protected $signature = 'feedback:diagnose {--kirim-tes : Kirim 1 email tes SUNGGUHAN (sinkron, bypass antrean) ke alamat tujuan}';

    protected $description = 'Cek konfigurasi & (opsional) kirim tes nyata utk notifikasi email Masukan (feedback)';

    public function handle(): int
    {
        $this->info('=== 1. Alamat tujuan (config/feedback.php · env BTIVE_FEEDBACK_EMAIL) ===');
        $raw = trim((string) config('feedback.development_email', ''));
        $this->line('Resolved: ' . ($raw !== '' ? $raw : '(KOSONG)'));
        if ($raw === '') {
            $this->error('❌ Tidak ada alamat tujuan sama sekali → notifyDevelopmentTeam() diam-diam tak mengirim apa pun (lihat FeedbackController::notifyDevelopmentTeam, cek BTIVE_FEEDBACK_EMAIL di .env lalu php artisan config:clear).');

            return self::FAILURE;
        }
        $emails = array_values(array_filter(array_map('trim', explode(',', $raw))));
        $this->info('✔ Tujuan: ' . implode(', ', $emails));

        $this->newLine();
        $this->info('=== 2. Konfigurasi mail transport ===');
        $mailer = config('mail.default');
        $this->line("MAIL_MAILER : {$mailer}");
        if ($mailer === 'log') {
            $this->error('❌ MAIL_MAILER masih "log" → email cuma ditulis ke file log aplikasi, TIDAK PERNAH benar-benar dikirim lewat SMTP. Ganti ke "smtp" di .env lalu php artisan config:clear.');
        } elseif ($mailer === 'array') {
            $this->error('❌ MAIL_MAILER "array" → email ditahan di memori, tidak pernah dikirim (dipakai khusus testing).');
        } else {
            $this->info("✔ Transport bukan log/array ({$mailer}).");
            $cfg = config("mail.mailers.{$mailer}", []);
            foreach (['host', 'port', 'encryption', 'username'] as $k) {
                if (array_key_exists($k, $cfg)) {
                    $this->line('  ' . $k . ': ' . ($cfg[$k] !== null && $cfg[$k] !== '' ? $cfg[$k] : '(kosong)'));
                }
            }
        }

        $this->newLine();
        $this->info('=== 3. Konfigurasi queue (FeedbackSubmittedNotification implements ShouldQueue) ===');
        $queueConn = config('queue.default');
        $this->line("QUEUE_CONNECTION : {$queueConn}");
        if ($queueConn !== 'sync') {
            $this->warn('⚠ Notifikasi feedback masuk ANTREAN dulu, bukan langsung terkirim. Butuh worker aktif (php artisan queue:work) atau cron, kalau tidak job menumpuk diam-diam.');
            try {
                $pending = DB::table('jobs')->count();
                $this->line("  Job menumpuk di tabel 'jobs' saat ini: {$pending}");
                if ($pending > 0) {
                    $this->warn("  → Ada {$pending} job MENUMPUK saat ini. Indikasi kuat: worker TIDAK berjalan (atau berhenti/crash).");
                }
            } catch (Throwable $e) {
                $this->line('  (tak bisa baca tabel jobs: ' . $e->getMessage() . ')');
            }
            try {
                $failed = DB::table('failed_jobs')->count();
                $this->line("  Job GAGAL tercatat di 'failed_jobs': {$failed}");
                if ($failed > 0) {
                    $last = DB::table('failed_jobs')
                        ->where('payload', 'like', '%FeedbackSubmittedNotification%')
                        ->latest('failed_at')->first();
                    if ($last) {
                        $this->error('❌ Ditemukan job feedback yg GAGAL diproses worker. Exception asli (dari saat worker terakhir mencoba):');
                        $this->error('  ' . Str::limit((string) $last->exception, 1000));
                    }
                }
            } catch (Throwable $e) {
                $this->line('  (tak bisa baca tabel failed_jobs: ' . $e->getMessage() . ')');
            }
        } else {
            $this->info('✔ QUEUE_CONNECTION=sync → notifikasi feedback terkirim LANGSUNG saat submit, tak butuh worker.');
        }

        if (! $this->option('kirim-tes')) {
            $this->newLine();
            $this->comment('Jalankan lagi dengan --kirim-tes utk benar-benar mengirim 1 email tes SINKRON (bypass antrean) ke alamat di atas — baru dari situ kelihatan error SMTP sungguhan kalau ada (host/port/auth/TLS/firewall).');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('=== 4. Tes kirim SUNGGUHAN (dipaksa sinkron utk proses CLI ini saja, bypass antrean) ===');
        // Notification pakai SerializesModels — bahkan dgn queue dipaksa "sync", Laravel tetap
        // mem-serialize lalu meng-hydrate ulang $feedback (query ulang by uuid). Kalau modelnya
        // tak pernah disimpan, hydrate itu gagal dgn ModelNotFoundException — jadi baris tes ini
        // WAJIB baris sungguhan di DB (dihapus lagi di akhir, bukan kartu di menu Masukan).
        $dummy = UserFeedback::create([
            'category' => 'lainnya',
            'status' => 'baru',
            'rating' => 5,
            'subject' => '[Diagnostik] Tes email feedback',
            'message' => 'Ini email tes dari `php artisan feedback:diagnose --kirim-tes`. Kalau Anda menerima ini, konfigurasi SMTP & alamat tujuan sudah benar — sisa kemungkinan penyebab tinggal soal worker antrean (lihat bagian 3 di atas) utk pengiriman feedback SUNGGUHAN dari user.',
            'context_url' => null,
        ]);

        config(['queue.default' => 'sync']); // paksa sinkron khusus proses CLI ini, tak menyentuh .env

        try {
            $route = count($emails) === 1 ? $emails[0] : $emails;
            Notification::route('mail', $route)->notify(new FeedbackSubmittedNotification($dummy));
            $this->info('✔ Terkirim TANPA exception. Cek inbox (& folder SPAM) di: ' . implode(', ', $emails));
        } catch (Throwable $e) {
            $this->error('❌ GAGAL saat mengirim — INI KEMUNGKINAN BESAR AKAR MASALAHNYA:');
            $this->error('  Class   : ' . get_class($e));
            $this->error('  Message : ' . $e->getMessage());
            $this->newLine();
            $this->warn('  Penyebab umum:');
            $this->warn('  - "Connection could not be established" / cURL timeout → firewall hosting blokir outbound ke port SMTP, atau host/port salah.');
            $this->warn('  - "Failed to authenticate" → username/password SMTP salah, atau perlu App Password khusus (Gmail/Google Workspace mewajibkan ini kalau 2FA aktif).');
            $this->warn('  - "stream_socket_enable_crypto" / TLS error → MAIL_ENCRYPTION tak cocok dgn MAIL_PORT (465=ssl, 587=tls).');

            return self::FAILURE;
        } finally {
            $dummy->delete(); // baris tes tak boleh nyangkut di menu Masukan admin
        }

        return self::SUCCESS;
    }
}
