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
        <a href="{{ route('ujian.create') }}" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i> Buat Ujian
        </a>
        @endcan
    </div>

    <div class="grid gap-3">
        @forelse($ujians as $ujian)
        <div class="card p-5 flex items-center justify-between gap-4 hover:border-primary transition">
            <a href="{{ route('ujian.show', $ujian) }}" class="min-w-0 flex-1">
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
                <h2 class="font-bold text-slate-800 dark:text-slate-100 mt-1 truncate">{{ $ujian->judul }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ $ujian->pelajaran?->nama }} · {{ $ujian->soal_count }} soal · {{ $ujian->kelas->count() }} kelas · {{ $ujian->durasi_menit }} menit
                </p>
            </a>
            <div class="flex items-center gap-2 flex-shrink-0">
                @can('manage', $ujian)
                    @if($ujian->status === 'published')
                        @if($ujian->kelas->count() > 0)
                        <form method="POST" action="{{ route('ujian.token.reset', $ujian) }}" onsubmit="return confirmAction(this, 'Buat token baru untuk SEMUA kelas ujian ini? Token lama tidak berlaku lagi.', 'orange')">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700">Reset Token</button>
                        </form>
                        @endif
                        <form method="POST" action="{{ route('ujian.close', $ujian) }}" onsubmit="return confirmAction(this, 'Tutup ujian ini? Siswa tidak bisa lagi memulai/melanjutkan.', 'red')">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-rose-600 text-white hover:bg-rose-700">Tutup</button>
                        </form>
                    @endif
                @endcan
                <a href="{{ route('ujian.show', $ujian) }}"><i data-lucide="chevron-right" class="w-5 h-5 text-slate-400"></i></a>
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
