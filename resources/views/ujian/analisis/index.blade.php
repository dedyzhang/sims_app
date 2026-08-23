@extends('layouts.app')
@section('title', 'Analisis Hasil — ' . $ujian->judul)

@section('content')
<div class="max-w-2xl mx-auto space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1">
            <a href="{{ route('ujian.index') }}" class="hover:underline">Ujian</a> /
            <a href="{{ route('ujian.show', $ujian) }}" class="hover:underline">{{ $ujian->judul }}</a> / Analisis Hasil
        </nav>
        <h1 class="page-title">Analisis Hasil Ujian</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Unduh rekap analisis per kelas (skor per soal + huruf jawaban objektif) dalam format Excel.</p>
    </div>

    <div class="space-y-3">
        @forelse($kelasBisaDiunduh as $uk)
        <div class="card p-5 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="font-semibold text-sm text-slate-800 dark:text-slate-100">Kelas {{ $uk->kelas?->tingkat }}{{ $uk->kelas?->kelas }}</p>
                @if($uk->guruPengampu)
                <p class="text-xs text-slate-400">Pengampu: {{ $uk->guruPengampu->nama }}</p>
                @endif
            </div>
            <a href="{{ route('ujian.analisis.unduh', [$ujian, $uk]) }}" class="btn-primary px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap">
                <i data-lucide="download" class="w-3.5 h-3.5 inline"></i> Unduh Excel
            </a>
        </div>
        @empty
        <div class="card p-10 text-center text-slate-400">
            <i data-lucide="folder-x" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
            <p class="text-sm font-medium">Tidak ada kelas yang bisa diunduh analisisnya.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
