<?php

namespace App\Console\Commands;

use App\Models\GrupChat;
use App\Models\GrupChatMember;
use App\Models\Kelas;
use App\Models\Semester;
use App\Services\GrupChatService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Backfill & rekonsiliasi keanggotaan Grup Chat.
 *
 * Dijadwalkan tiap malam, dan itu WAJIB — bukan kemewahan. Kolom siswa.id_kelas
 * dimutasi dari beberapa jalur kode (SiswaController::store/update,
 * KelasController::saveRombel, AlumniController::luluskan) dan mungkin juga dari
 * impor Excel atau SQL mentah yang tidak akan pernah memanggil GrupChatService.
 * Rekonsiliasi mengubah "ada titik sync yang terlewat" dari bug permanen
 * (ex-siswa membaca grup kelas lamanya selamanya) menjadi inkonsistensi <= 24 jam.
 *
 * Command ini TIDAK PERNAH mengarsipkan grup — pergantian tahun ajaran ditangani
 * grupchat:tahun-baru secara manual, supaya satu kesalahan tanda "semester aktif"
 * tidak mengarsipkan seluruh sekolah tanpa sepengetahuan siapa pun.
 */
class GrupChatSinkron extends Command
{
    protected $signature = 'grupchat:sinkron
                            {--tahun= : Tahun ajaran, mis. 2025/2026 (default: semester aktif)}
                            {--kelas= : Batasi ke satu kelas (uuid)}
                            {--dry-run : Tampilkan rencana tanpa menulis apa pun}';

    protected $description = 'Buat grup chat per kelas dan rekonsiliasi keanggotaannya';

    public function handle(GrupChatService $service): int
    {
        $tahun = $this->option('tahun');

        if (! $tahun && ! Semester::aktif()) {
            $this->error('Tidak ada semester aktif dan --tahun tidak diberikan.');
            $this->line('Set semester aktif di Pengaturan, atau jalankan: php artisan grupchat:sinkron --tahun=2025/2026');

            return self::FAILURE;
        }

        $kelas = Kelas::query()
            ->when($this->option('kelas'), fn ($q, $uuid) => $q->where('uuid', $uuid))
            ->orderBy('tingkat')->orderBy('kelas')
            ->get();

        if ($kelas->isEmpty()) {
            $this->warn('Tidak ada kelas yang cocok.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            return $this->rencana($kelas, $tahun);
        }

        $bar = $this->output->createProgressBar($kelas->count());
        $bar->start();

        // Isolasi per kelas: satu kelas gagal (query timeout, deadlock, data
        // anomali) tidak boleh menggagalkan seluruh batch dan membuat kelas-kelas
        // SETELAHNYA ikut terlewat malam itu — command ini ada justru supaya
        // celah sync tidak pernah lebih dari ~24 jam (lihat docblock kelas ini).
        $gagal = [];
        foreach ($kelas as $k) {
            try {
                $service->syncKelas($k, $tahun);
            } catch (Throwable $e) {
                $gagal[] = $k->nama_lengkap;
                Log::error("grupchat:sinkron gagal untuk kelas {$k->nama_lengkap}: {$e->getMessage()}", [
                    'kelas_uuid' => $k->uuid,
                    'exception' => $e,
                ]);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            'Selesai: %d kelas, %d grup, %d anggota aktif.',
            $kelas->count(),
            GrupChat::count(),
            GrupChatMember::whereNull('left_at')->count(),
        ));

        if ($gagal !== []) {
            $this->error(sprintf('Gagal disinkron (%d kelas): %s', count($gagal), implode(', ', $gagal)));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function rencana($kelas, ?string $tahun): int
    {
        $tahunEfektif = $tahun ?? Semester::aktif()?->tahun;
        $this->line("Tahun ajaran: <info>{$tahunEfektif}</info>");
        $this->newLine();

        $baris = [];
        foreach ($kelas as $k) {
            $adaKelas = GrupChat::where('id_kelas', $k->uuid)
                ->where('tipe', GrupChat::TIPE_KELAS)
                ->where('tahun_ajaran', $tahunEfektif)->exists();
            $adaPaguyuban = GrupChat::where('id_kelas', $k->uuid)
                ->where('tipe', GrupChat::TIPE_PAGUYUBAN)
                ->where('tahun_ajaran', $tahunEfektif)->exists();

            $baris[] = [
                $k->nama_lengkap,
                $adaKelas ? 'ada' : 'akan dibuat',
                $adaPaguyuban ? 'ada' : 'akan dibuat',
            ];
        }

        $this->table(['Kelas', 'Grup Kelas', 'Grup Paguyuban'], $baris);
        $this->comment('Dry run — tidak ada perubahan yang ditulis. Keanggotaan tetap direkonsiliasi saat dijalankan tanpa --dry-run.');

        return self::SUCCESS;
    }
}
