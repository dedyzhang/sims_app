<?php
require 'vendor/autoload.php';
\ = require_once 'bootstrap/app.php';
\ = \->make(Illuminate\Contracts\Console\Kernel::class);
\->bootstrap();

\ = \App\Models\Poin::query()
    ->join('aturans', 'poin.id_aturan', '=', 'aturans.uuid')
    ->selectRaw("
        poin.id_siswa,
        SUM(CASE WHEN aturans.jenis = 'kurang' THEN -(aturans.poin) ELSE aturans.poin END) as delta_poin,
        SUM(CASE WHEN aturans.jenis = 'tambah' THEN aturans.poin ELSE 0 END) as total_tambah,
        COUNT(poin.uuid) as total_aktivitas
    ")
    ->groupBy('poin.id_siswa')
    ->get();
dump(\->first());
