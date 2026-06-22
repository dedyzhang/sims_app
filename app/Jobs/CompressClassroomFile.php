<?php

namespace App\Jobs;

use App\Services\FileCompressionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Kompresi file via queue (opsi async). Memproses file sementara lalu membuat baris
 * di tabel lampiran terkait. Dipakai bila config('classroom.compress_sync') = false.
 */
class CompressClassroomFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $tempPath,
        public string $originalName,
        public string $mime,
        public string $subdir,
        public string $fileModel,   // FQCN model lampiran
        public string $fkColumn,    // mis. material_id
        public string $fkId,
        public int $sortOrder = 0,
        public bool $withSort = false,
    ) {}

    public function handle(FileCompressionService $svc): void
    {
        if (!is_file($this->tempPath)) {
            Log::warning("CompressClassroomFile: file sementara hilang: {$this->tempPath}");
            return;
        }

        // Rekonstruksi UploadedFile dari file sementara (test mode = lewati cek is_uploaded_file).
        $file = new UploadedFile($this->tempPath, $this->originalName, $this->mime, null, true);
        $meta = $svc->handle($file, $this->subdir);

        $data = array_merge([$this->fkColumn => $this->fkId], $meta);
        if ($this->withSort) {
            $data['sort_order'] = $this->sortOrder;
        }
        ($this->fileModel)::create($data);

        @unlink($this->tempPath);
    }
}
