@extends('layouts.app')
@section('title', 'Bank Soal')

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <div>
        <h1 class="page-title">Bank Soal</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Kumpulan soal per mapel yang bisa disimpan sekali lalu disisipkan berulang kali ke Ujian.</p>
    </div>

    <div class="grid gap-3">
        @forelse($pelajaran as $p)
        <a href="{{ route('bank-soal.show', $p) }}" class="card p-5 flex items-center justify-between gap-4 hover:border-primary transition">
            <div class="min-w-0">
                <h2 class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ $p->nama }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $jumlahPerPelajaran[$p->uuid] ?? 0 }} soal tersimpan</p>
            </div>
            <i data-lucide="chevron-right" class="w-5 h-5 text-slate-400 flex-shrink-0"></i>
        </a>
        @empty
        <div class="card p-10 text-center text-slate-400">
            <i data-lucide="library" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
            <p class="text-sm font-medium">Belum ada mapel yang bisa dikelola bank soalnya.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
