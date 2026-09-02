<?php

namespace App\Console\Commands;

use App\Models\UjianSesi;
use Illuminate\Console\Command;

/**
 * Sesi ujian "yatim" (UjianJadwal-nya sudah dihapus admin, mis. krn set jadwal ulang) yg
 * TAK PERNAH punya Berita Acara/Daftar Hadir sama sekali — murni baris kosong sisa, dulu
 * bikin baris hantu "AD-HOC/Tanpa Jadwal" di halaman Rekap. Tampilan sudah difilter di
 * UjianRekapController::sesiPunyaData(), tapi baris LAMA yg sudah terlanjur nyangkut di DB
 * (mis. di produksi) perlu dibersihkan manual sekali via command ini — jalankan dari
 * terminal/SSH hosting. Sesi yatim yg SUDAH terlanjur py BA/daftar hadir TIDAK disentuh
 * (data historis asli, sengaja dipertahankan).
 */
class UjianBersihkanSesiYatim extends Command
{
    protected $signature = 'ujian:bersihkan-sesi-yatim {--dry-run : Tampilkan yang akan dihapus tanpa benar-benar menghapus}';

    protected $description = 'Hapus sesi ujian yatim (jadwal sudah dihapus) yang tak punya Berita Acara/Daftar Hadir sama sekali';

    public function handle(): int
    {
        $sesiList = UjianSesi::whereDoesntHave('jadwal')
            ->whereDoesntHave('beritaAcara')
            ->whereDoesntHave('daftarHadir')
            ->with('paket')
            ->get();

        if ($sesiList->isEmpty()) {
            $this->info('Tidak ada sesi yatim kosong yang perlu dibersihkan.');

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$sesiList->count()} sesi yatim kosong:");
        foreach ($sesiList as $sesi) {
            $label = $sesi->label ? " (Sesi {$sesi->label})" : '';
            $this->line("  - {$sesi->paket?->nama} · {$sesi->tanggal->toDateString()} {$sesi->jam_mulai}-{$sesi->jam_selesai}{$label}");
        }

        if ($this->option('dry-run')) {
            $this->warn('Mode --dry-run — tidak ada yang dihapus. Jalankan tanpa --dry-run untuk benar-benar menghapus.');

            return self::SUCCESS;
        }

        $count = UjianSesi::whereIn('uuid', $sesiList->pluck('uuid'))->delete();
        $this->info("Selesai — {$count} sesi yatim kosong dihapus.");

        return self::SUCCESS;
    }
}
