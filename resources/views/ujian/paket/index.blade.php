@extends('layouts.app')
@section('title', 'Paket Ujian')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Paket Ujian</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Kelompokkan beberapa ujian jadi satu periode formal — mis. "PAS Semester 1 2026/2027" — lengkap dengan ruangan, jadwal, dan pengawas.</p>
        </div>
        <a href="{{ route('ujian.paket.create') }}" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i> Buat Paket
        </a>
    </div>

    <div class="grid gap-3">
        @forelse($paketList as $paket)
        <a href="{{ route('ujian.paket.show', $paket) }}" class="card p-5 flex items-center justify-between gap-4 hover:border-primary transition">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ $paket->jenisLabel() }}</span>
                    @php
                        $statusBadge = match($paket->status) {
                            'berjalan' => 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300',
                            'selesai'  => 'bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300',
                            default    => 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300',
                        };
                    @endphp
                    <span class="badge {{ $statusBadge }}">{{ $paket->statusLabel() }}</span>
                    @if($paket->semester)
                    <span class="text-xs text-slate-400">Semester {{ $paket->semester->semester }} · {{ $paket->semester->tahun }}</span>
                    @endif
                </div>
                <h2 class="font-bold text-slate-800 dark:text-slate-100 mt-1 truncate">{{ $paket->nama }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ $paket->ujian_count }} ujian · {{ $paket->ruangan_count }} ruangan
                    @if($paket->tanggal_mulai) · {{ $paket->tanggal_mulai->translatedFormat('d M Y') }}@if($paket->tanggal_selesai) – {{ $paket->tanggal_selesai->translatedFormat('d M Y') }}@endif @endif
                </p>
            </div>
            <i data-lucide="chevron-right" class="w-5 h-5 text-slate-400 flex-shrink-0"></i>
        </a>
        @empty
        <div class="card p-10 text-center text-slate-400">
            <i data-lucide="folder-check" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
            <p class="text-sm font-medium">Belum ada paket ujian dibuat.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
