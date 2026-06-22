<?php

namespace App\Http\Controllers\Concerns;

use App\Jobs\CompressClassroomFile;
use App\Services\FileCompressionService;
use Illuminate\Http\UploadedFile;

/**
 * Helper bersama: lampirkan banyak file ke materi/tugas/submission lewat
 * FileCompressionService. Sinkron (default) atau queued (config compress_sync=false).
 */
trait HandlesClassroomUploads
{
    /**
     * @param  UploadedFile[]  $files
     * @param  string  $fileModel  FQCN model lampiran (mis. ClassroomMaterialFile::class)
     */
    protected function attachUploads(array $files, string $subdir, string $fileModel, string $fkColumn, string $fkId, bool $withSort = false): void
    {
        $svc = app(FileCompressionService::class);

        foreach (array_values(array_filter($files)) as $i => $file) {
            if (config('classroom.compress_sync', true)) {
                // Sinkron: kompres sekarang, buat baris file.
                $meta = $svc->handle($file, $subdir);
                $data = array_merge([$fkColumn => $fkId], $meta);
                if ($withSort) {
                    $data['sort_order'] = $i;
                }
                $fileModel::create($data);
            } else {
                // Async: simpan sementara, proses via queue.
                $tmp = $file->store('tmp/classroom', 'local');
                CompressClassroomFile::dispatch(
                    storage_path('app/' . $tmp),
                    $file->getClientOriginalName(),
                    $file->getMimeType() ?? 'application/octet-stream',
                    $subdir, $fileModel, $fkColumn, $fkId, $i, $withSort
                );
            }
        }
    }
}
