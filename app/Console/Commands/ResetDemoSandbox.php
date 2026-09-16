<?php

namespace App\Console\Commands;

use App\Services\DemoResetService;
use Illuminate\Console\Command;

class ResetDemoSandbox extends Command
{
    protected $signature = 'demo:reset-sandbox {--dry-run : Hitung baris tanpa menghapus}';

    protected $description = 'Reset data operasional sintetis sandbox tanpa menghapus akun/akses demo';

    public function handle(DemoResetService $reset): int
    {
        try {
            $result = $reset->run((bool) $this->option('dry-run'));
        } catch (\Throwable $e) {
            // DemoResetService melempar ulang apa pun yang ditangkapnya (termasuk
            // QueryException: lock timeout / masalah FK saat menghapus tabel). Kalau
            // hanya RuntimeException yang ditangkap, run terjadwal 02:00 mati dengan
            // exception mentah alih-alih FAILURE yang bersih + tercatat.
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($result['message']);
        $counts = $result['run']->table_counts ?? [];
        foreach ($counts as $table => $count) {
            $this->line($table.': '.$count);
        }

        return self::SUCCESS;
    }
}
