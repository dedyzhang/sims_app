<?php

return [
    // true = kompresi sinkron saat request (default, aman tanpa worker queue).
    // false = dikompres lewat queued job (butuh `php artisan queue:work` aktif).
    'compress_sync' => env('CLASSROOM_COMPRESS_SYNC', true),

    // Path biner Ghostscript bila tidak ada di PATH (mis. 'C:\\Program Files\\gs\\bin\\gswin64c.exe').
    'gs_bin' => env('GHOSTSCRIPT_BIN'),

    // Batas upload.
    'max_files'   => 10,   // maks file per upload
    'max_file_mb' => 20,   // maks ukuran per file sebelum kompres (MB)
];
