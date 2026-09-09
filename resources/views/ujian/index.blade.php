@extends('layouts.app')
@section('title', 'Ujian')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Ujian</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Ujian formal (Harian/PTS/PAS/UAS) — bank soal, anti-kecurangan, transfer nilai otomatis.</p>
        </div>
        @can('create', \App\Models\Ujian::class)
        <div class="flex gap-2" x-data="{ openRestore: false }">
            <button type="button" @click="openRestore = true" class="btn-secondary px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
                <i data-lucide="upload-cloud" class="w-4 h-4"></i> Restore Backup
            </button>
            <a href="{{ route('ujian.create') }}" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> Buat Ujian
            </a>

            <div x-show="openRestore" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm" style="display: none;">
                <form method="POST" action="{{ route('ujian.restore') }}" enctype="multipart/form-data" class="bg-white dark:bg-slate-800 rounded-2xl p-6 max-w-md w-full space-y-4 shadow-xl" @click.outside="openRestore = false">
                    @csrf
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">Restore Ujian dari Backup</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Pilih file <code>.json</code> hasil backup ujian sebelumnya untuk memulihkan data ujian beserta nilai siswa.</p>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">File Backup (.json)</label>
                            <input type="file" name="backup_file" accept=".json" required class="form-input w-full p-2 border border-slate-300 rounded">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Password Admin</label>
                            <input type="password" name="password" required class="form-input w-full p-2 border border-slate-300 rounded" placeholder="Password Anda">
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="openRestore = false" class="btn-secondary px-4 py-2 rounded-xl text-sm">Batal</button>
                        <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-sm font-bold">Mulai Restore</button>
                    </div>
                </form>
            </div>
        </div>
        @endcan
    </div>

    <div class="grid gap-3">
        @forelse($ujians as $ujian)
        <div class="card p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 hover:border-primary transition group">
            <a href="{{ route('ujian.show', $ujian) }}" class="min-w-0 flex-1 block">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ $ujian->jenisLabel() }}</span>
                    @php
                        $statusBadge = match($ujian->status) {
                            'published' => 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300',
                            'closed' => 'bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300',
                            default => 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300',
                        };
                    @endphp
                    <span class="badge {{ $statusBadge }}">{{ $ujian->statusLabel() }}</span>
                </div>
                <h2 class="font-bold text-slate-800 dark:text-slate-100 mt-1.5 truncate">{{ $ujian->judul }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    {{ $ujian->pelajaran?->nama }} &middot; {{ $ujian->soal_count }} soal &middot; {{ $ujian->kelas->count() }} kelas &middot; {{ $ujian->durasi_menit }} menit
                </p>
            </a>
            <div class="flex flex-wrap items-center gap-2 sm:flex-shrink-0 mt-1 sm:mt-0 pt-2 sm:pt-0 border-t sm:border-0 border-slate-100 dark:border-slate-800/50 justify-between sm:justify-end">
                <div class="flex items-center gap-2">
                    @can('manage', $ujian)
                        @if($ujian->status === 'published')
                            @if($ujian->kelas->count() > 0)
                            <form method="POST" action="{{ route('ujian.token.reset', $ujian) }}" onsubmit="return confirmAction(this, 'Buat token baru untuk SEMUA kelas ujian ini? Token lama tidak berlaku lagi.', 'orange')">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 rounded-lg text-[11px] sm:text-xs font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700">Reset Token</button>
                            </form>
                            @endif
                            <form method="POST" action="{{ route('ujian.close', $ujian) }}" onsubmit="return confirmAction(this, 'Tutup ujian ini? Siswa tidak bisa lagi memulai/melanjutkan.', 'red')">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 rounded-lg text-[11px] sm:text-xs font-bold bg-rose-600 text-white hover:bg-rose-700">Tutup</button>
                            </form>
                        @endif
                    @endcan
                </div>
                <a href="{{ route('ujian.show', $ujian) }}" class="hidden sm:flex"><i data-lucide="chevron-right" class="w-5 h-5 text-slate-400 group-hover:text-primary transition"></i></a>
            </div>
        </div>
        @empty
        <div class="card p-10 text-center text-slate-400">
            <i data-lucide="file-check-2" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
            <p class="text-sm font-medium">Belum ada ujian yang dibuat.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection


