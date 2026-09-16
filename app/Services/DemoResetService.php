<?php

namespace App\Services;

use App\Models\DemoResetRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DemoResetService
{
    /**
     * @return array{ok: bool, run: DemoResetRun, message: string}
     */
    public function run(bool $dryRun = false): array
    {
        if (! filter_var(config('demo.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            throw new \RuntimeException('DEMO_TEMPORARILY_UNAVAILABLE');
        }

        $lock = Cache::lock('demo-reset-sandbox', (int) config('demo.reset.lock_seconds', 600));
        if (! $lock->get()) {
            throw new \RuntimeException('RESET_LOCK_BUSY');
        }

        try {
            return $this->execute($dryRun);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{ok: bool, run: DemoResetRun, message: string}
     */
    private function execute(bool $dryRun): array
    {
        $tables = array_values(array_filter(array_map('strval', (array) config('demo.reset.truncate', []))));
        $forbidden = array_values(array_filter(array_map('strval', (array) config('demo.reset.forbidden', []))));
        $version = (string) config('demo.reset.manifest_version', '1.0');

        $run = DemoResetRun::query()->create([
            'manifest_version' => $version,
            'status' => DemoResetRun::STATUS_STARTED,
            'dry_run' => $dryRun,
            'started_at' => now(),
        ]);

        try {
            $this->assertManifest($tables, $forbidden);

            $counts = [];
            foreach ($tables as $table) {
                $counts[$table] = DB::table($table)->count();
            }

            if ($dryRun) {
                $run->forceFill([
                    'status' => DemoResetRun::STATUS_DRY_RUN,
                    'table_counts' => $counts,
                    'finished_at' => now(),
                ])->save();

                return ['ok' => true, 'run' => $run, 'message' => 'Dry-run: tidak ada baris yang dihapus.'];
            }

            Schema::disableForeignKeyConstraints();
            try {
                foreach ($tables as $table) {
                    DB::table($table)->delete();
                }
            } finally {
                Schema::enableForeignKeyConstraints();
            }

            $run->forceFill([
                'status' => DemoResetRun::STATUS_SUCCESS,
                'table_counts' => $counts,
                'finished_at' => now(),
            ])->save();

            Log::info('demo.reset.success', [
                'run_id' => $run->id,
                'tables' => count($tables),
            ]);

            return ['ok' => true, 'run' => $run, 'message' => 'Reset sandbox selesai.'];
        } catch (\Throwable $e) {
            $code = $e->getMessage();
            if (strlen($code) > 80 || ! preg_match('/^[A-Z0-9_]+$/', $code)) {
                $code = 'RESET_FAILED';
            }

            $run->forceFill([
                'status' => DemoResetRun::STATUS_FAILED,
                'error_code' => $code,
                'finished_at' => now(),
            ])->save();

            Log::error('demo.reset.failed', [
                'run_id' => $run->id,
                'error_code' => $code,
            ]);

            throw $e;
        }
    }

    /**
     * @param  list<string>  $tables
     * @param  list<string>  $forbidden
     */
    private function assertManifest(array $tables, array $forbidden): void
    {
        if ($tables === []) {
            throw new \RuntimeException('RESET_MANIFEST_EMPTY');
        }

        foreach ($tables as $table) {
            if (in_array($table, $forbidden, true)) {
                throw new \RuntimeException('RESET_FORBIDDEN_TABLE');
            }

            if (! Schema::hasTable($table)) {
                throw new \RuntimeException('RESET_UNKNOWN_TABLE');
            }
        }
    }
}
