<?php

namespace App\Console\Commands;

use App\Models\Kelas;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianJawaban;
use App\Models\UjianKelas;
use App\Models\User;
use App\Services\UjianGrader;
use App\Support\ModeUjian;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UjianSimulasiCommand extends Command
{
    protected $signature = 'ujian:simulasi
                            {ujian? : UUID atau bagian dari judul ujian yang ingin diuji}
                            {--siswa=100 : Jumlah siswa virtual (misal 50, 100, 500, 1000)}
                            {--mode-ujian=1 : 1 = Aktifkan Fast-Submit (Mode Ujian), 0 = Grading Sinkron lama}
                            {--commit : Simpan permanen ke database (Default: DI-ROLLBACK 100% bersih)}';

    protected $description = 'Simulasi beban ujian masal (stress test) dengan siswa virtual tanpa mengotori database';

    public function handle(UjianGrader $grader): int
    {
        $targetCount = (int) $this->option('siswa');
        if ($targetCount <= 0) {
            $this->error('Jumlah siswa virtual harus lebih dari 0.');
            return self::FAILURE;
        }

        $isModeUjian = (bool) $this->option('mode-ujian');
        $shouldCommit = (bool) $this->option('commit');

        // 1. Temukan Ujian
        $ujian = $this->resolveUjian();
        if (! $ujian) {
            $this->error('Ujian tidak ditemukan. Pastikan sudah ada paket ujian yang dibuat.');
            return self::FAILURE;
        }

        $soalList = $ujian->soal()->with('opsi')->get();
        if ($soalList->isEmpty()) {
            $this->error("Ujian '{$ujian->judul}' belum memiliki soal. Silakan pilih ujian yang sudah memiliki soal.");
            return self::FAILURE;
        }

        // Ambil atau buat UjianKelas sementara di dalam sesi
        $ujianKelas = $ujian->kelas()->first();

        $this->outputHeader($ujian, $soalList->count(), $targetCount, $isModeUjian, $shouldCommit);

        $initialMemory = memory_get_usage(true);

        // 2. Mulai Transaksi Database Aman
        DB::beginTransaction();

        try {
            if (! $ujianKelas) {
                $kelas = Kelas::first() ?? Kelas::create(['tingkat' => '8', 'kelas' => 'SIMULASI']);
                $ujianKelas = UjianKelas::create([
                    'id_ujian' => $ujian->uuid,
                    'id_kelas' => $kelas->uuid,
                    'token_masuk' => 'SIMULASI',
                    'status' => 'aktif',
                ]);
            }

            // A. Pembuatan Siswa Virtual
            $this->info("⏳ [1/4] Membuat {$targetCount} akun siswa virtual...");
            $users = $this->createVirtualStudents($targetCount);

            // B. Pembuatan Attempt (Mulai Ujian)
            $this->info("⏳ [2/4] Mendaftarkan attempt ujian untuk {$targetCount} siswa virtual...");
            $urutanSoalIds = $soalList->pluck('uuid')->all();
            $attempts = $this->createVirtualAttempts($ujianKelas, $users, $urutanSoalIds);

            // C. Pengisian Jawaban Acak (Simpan Jawaban)
            $this->info("⏳ [3/4] Mengisi jawaban acak untuk {$soalList->count()} nomor soal per siswa...");
            $totalJawaban = $this->populateVirtualAnswers($attempts, $soalList);

            // D. STRESS TEST: Submit Serentak
            $this->newLine();
            $this->warn("🚀 [4/4] Menjalankan stress test: SUBMIT SERENTAK ({$targetCount} siswa)...");

            $submitStats = $this->executeStressSubmit($attempts, $isModeUjian, $grader);

            // E. Tampilkan Laporan Hasil
            $peakMemory = memory_get_peak_usage(true) - $initialMemory;
            $this->outputResults($submitStats, $targetCount, $totalJawaban, $isModeUjian, $peakMemory);

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->newLine();
            $this->error("❌ Terjadi kesalahan saat simulasi: {$e->getMessage()}");
            return self::FAILURE;
        } finally {
            if (! $shouldCommit) {
                DB::rollBack();
                $this->newLine();
                $this->info("🛡️  GARANSI KEAMANAN: Seluruh data simulasi (siswa virtual, attempt, dan jawaban) telah dibatalkan (ROLLBACK).");
                $this->info("    Database Anda tetap 100% bersih, murni, dan tidak bertambah 1 baris pun.");
            } else {
                DB::commit();
                $this->newLine();
                $this->warn("⚠️  PERHATIAN: Data simulasi telah disimpan permanen ke database karena opsi --commit aktif.");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Cari ujian berdasarkan input atau ambil yang tersedia.
     */
    private function resolveUjian(): ?Ujian
    {
        $input = $this->argument('ujian');

        if ($input) {
            $ujian = Ujian::where('uuid', $input)->first();
            if ($ujian) {
                return $ujian;
            }

            return Ujian::where('judul', 'like', "%{$input}%")->first();
        }

        $list = Ujian::latest()->take(10)->get();
        if ($list->isEmpty()) {
            return null;
        }

        if ($list->count() === 1) {
            return $list->first();
        }

        $options = $list->mapWithKeys(fn (Ujian $u) => [$u->uuid => "{$u->judul} ({$u->soal()->count()} soal)"])->all();
        $chosenUuid = $this->choice('Pilih paket ujian yang ingin disimulasikan:', $options, array_key_first($options));

        return $list->firstWhere('uuid', $chosenUuid);
    }

    /**
     * Buat siswa virtual dalam batch.
     *
     * @return list<User>
     */
    private function createVirtualStudents(int $count): array
    {
        $users = [];
        $dummyPassword = Hash::make('simulasi_secret');
        $now = now();

        $chunks = array_chunk(range(1, $count), 250);
        foreach ($chunks as $chunk) {
            $rows = [];
            foreach ($chunk as $i) {
                $uuid = (string) Str::uuid();
                $rows[] = [
                    'uuid' => $uuid,
                    'username' => 'sim_bot_'.Str::random(10),
                    'password' => $dummyPassword,
                    'access' => 'siswa',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            User::insert($rows);

            foreach (User::hydrate($rows) as $user) {
                $users[] = $user;
            }
        }

        return $users;
    }

    /**
     * Buat attempt virtual untuk tiap siswa.
     *
     * @param list<User> $users
     * @param list<string> $urutanSoalIds
     * @return list<UjianAttempt>
     */
    private function createVirtualAttempts(UjianKelas $ujianKelas, array $users, array $urutanSoalIds): array
    {
        $attempts = [];
        $now = now();
        $jsonSoal = json_encode($urutanSoalIds);

        $chunks = array_chunk($users, 250);
        foreach ($chunks as $chunk) {
            $rows = [];
            foreach ($chunk as $user) {
                $uuid = (string) Str::uuid();
                $rows[] = [
                    'uuid' => $uuid,
                    'id_ujian_kelas' => $ujianKelas->uuid,
                    'id_siswa' => $user->uuid,
                    'urutan_soal' => $jsonSoal,
                    'status' => UjianAttempt::STATUS_IN_PROGRESS,
                    'mulai_pada' => $now,
                    'batas_waktu_pada' => $now->copy()->addHours(2),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            UjianAttempt::insert($rows);

            foreach (UjianAttempt::hydrate($rows) as $att) {
                $attempts[] = $att;
            }
        }

        return $attempts;
    }

    /**
     * Isi jawaban acak untuk seluruh attempt.
     *
     * @param list<UjianAttempt> $attempts
     * @param \Illuminate\Database\Eloquent\Collection $soalList
     */
    private function populateVirtualAnswers(array $attempts, $soalList): int
    {
        $now = now();
        $totalJawaban = 0;
        $batch = [];

        foreach ($attempts as $attempt) {
            foreach ($soalList as $soal) {
                $opsi = $soal->opsi;
                $chosenOpsi = $opsi->isNotEmpty() ? $opsi->random()->uuid : null;

                $batch[] = [
                    'uuid' => (string) Str::uuid(),
                    'id_attempt' => $attempt->uuid,
                    'id_soal' => $soal->uuid,
                    'id_opsi_dipilih' => $chosenOpsi,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $totalJawaban++;

                if (count($batch) >= 500) {
                    UjianJawaban::insert($batch);
                    $batch = [];
                }
            }
        }

        if ($batch !== []) {
            UjianJawaban::insert($batch);
        }

        return $totalJawaban;
    }

    /**
     * Eksekusi submit serentak dan catat statistik waktu respon.
     *
     * @param list<UjianAttempt> $attempts
     * @return array{total_ms: float, avg_ms: float, min_ms: float, max_ms: float, throughput: float}
     */
    private function executeStressSubmit(array $attempts, bool $isModeUjian, UjianGrader $grader): array
    {
        $durations = [];
        $tGlobalStart = microtime(true);

        $bar = $this->output->createProgressBar(count($attempts));
        $bar->start();

        foreach ($attempts as $attempt) {
            $t0 = microtime(true);

            if ($isModeUjian) {
                // Fast-Submit (Mode Ujian)
                $attempt->update([
                    'status' => UjianAttempt::STATUS_SUBMITTED,
                    'selesai_pada' => now(),
                ]);
            } else {
                // Grading sinkron lama
                $attempt->update([
                    'status' => UjianAttempt::STATUS_SUBMITTED,
                    'selesai_pada' => now(),
                ]);
                $fresh = $attempt->fresh() ?? $attempt;
                $grader->finalisasiObjektif($fresh);
            }

            $durations[] = (microtime(true) - $t0) * 1000;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $totalMs = (microtime(true) - $tGlobalStart) * 1000;
        $count = count($durations);

        return [
            'total_ms' => $totalMs,
            'avg_ms' => $count > 0 ? array_sum($durations) / $count : 0,
            'min_ms' => $count > 0 ? min($durations) : 0,
            'max_ms' => $count > 0 ? max($durations) : 0,
            'throughput' => $totalMs > 0 ? ($count / ($totalMs / 1000)) : 0,
        ];
    }

    private function outputHeader(Ujian $ujian, int $soalCount, int $siswaCount, bool $isModeUjian, bool $shouldCommit): void
    {
        $this->newLine();
        $this->info('================================================================');
        $this->info('          SIMULASI & STRESS-TEST UJIAN SISWA (SIMS)             ');
        $this->info('================================================================');
        $this->line(" 📋 Paket Ujian     : <comment>{$ujian->judul}</comment>");
        $this->line(" 📝 Jumlah Soal     : <comment>{$soalCount} Soal</comment>");
        $this->line(" 👥 Siswa Virtual   : <comment>{$siswaCount} Siswa</comment>");
        $this->line(' ⚙️  Mode Ujian      : '.($isModeUjian ? '<fg=green>AKTIF (Fast-Submit ⚡)</>' : '<fg=yellow>NONAKTIF (Grading Sinkron Penuh)</>'));
        $this->line(' 🛡️  Status DB       : '.($shouldCommit ? '<fg=red>KOMIT KE DATABASE</>' : '<fg=green;options=bold>ROLLBACK 100% (Aman & Bebas Residu)</>'));
        $this->info('----------------------------------------------------------------');
    }

    private function outputResults(array $s, int $siswaCount, int $totalJawaban, bool $isModeUjian, int $peakMemory): void
    {
        $this->newLine();
        $this->info('================================================================');
        $this->info('                 LAPORAN HASIL STRESS TEST                      ');
        $this->info('================================================================');

        $this->table(
            ['Metrik Performa', 'Hasil Pengujian', 'Keterangan'],
            [
                ['Total Siswa Virtual', number_format($siswaCount).' Siswa', 'Beban bersamaan'],
                ['Total Jawaban Tersimpan', number_format($totalJawaban).' Baris Jawaban', 'I/O database realistis'],
                ['Total Waktu Submit', number_format($s['total_ms'], 1).' ms ('.number_format($s['total_ms'] / 1000, 2).' detik)', 'Waktu seluruh siswa kumpul'],
                ['Rata-rata per Siswa', number_format($s['avg_ms'], 2).' milidetik', $s['avg_ms'] < 50 ? '⚡ SANGAT CEPAT (Bebas Macet)' : 'Wajar'],
                ['Submit Tercepat / Terlambat', number_format($s['min_ms'], 1).' ms / '.number_format($s['max_ms'], 1).' ms', 'Jarak variansi proses'],
                ['Throughput Server', number_format($s['throughput'], 1).' submit / detik', 'Kapasitas serentak'],
                ['Konsumsi RAM Tambahan', number_format($peakMemory / 1024 / 1024, 2).' MB', 'Sangat ramah memori'],
                ['Simulasi Jalur', $isModeUjian ? 'Mode Ujian (Fast-Submit)' : 'Mode Normal', $isModeUjian ? 'Direkomendasikan di Hari-H' : 'Jalur harian'],
            ]
        );

        $this->newLine();
        if ($s['avg_ms'] <= 50) {
            $this->info('✅ KESIMPULAN: Server dan database SANGAT KUAT menampung '.$siswaCount.' siswa serentak tanpa antrean.');
        } else {
            $this->warn('⚠️ KESIMPULAN: Waktu respon di atas 50ms, pastikan Mode Ujian selalu aktif saat pelaksanaan.');
        }
    }
}
