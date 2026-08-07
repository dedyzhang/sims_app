@extends('layouts.app')
@section('title', 'Ujian Saya')

@section('content')
<div class="max-w-2xl mx-auto space-y-5">
    <div>
        <h1 class="page-title">Ujian Saya</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Daftar ujian yang ditetapkan untuk kelas Anda.</p>
    </div>

    <div class="grid gap-3">
        @forelse($ujianKelasList as $uk)
        @php
            $attempt = $attempts->get($uk->uuid);
            $sudahSelesai = $attempt && in_array($attempt->status, ['submitted','dinilai']);
        @endphp
        <div class="card p-5 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs text-slate-400">{{ $uk->ujian->pelajaran?->nama }} · {{ $uk->ujian->jenisLabel() }} · {{ $uk->ujian->durasi_menit }} menit</p>
                <h2 class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ $uk->ujian->judul }}</h2>
                <p class="text-xs mt-1">
                    @if($sudahSelesai)
                        <span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300">Sudah Dikumpulkan</span>
                    @elseif($attempt?->isLocked())
                        <span class="badge bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400">Terkunci</span>
                    @elseif($attempt)
                        <span class="badge bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300">Sedang Dikerjakan</span>
                    @elseif(!$uk->isOpenNow())
                        <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-500">Belum/Tidak Terbuka</span>
                    @else
                        <span class="badge bg-primary/10 text-primary">Siap Dikerjakan</span>
                    @endif
                </p>
            </div>
            @if($sudahSelesai)
                <a href="{{ route('ujian.siswa.hasil', [$uk->ujian, $attempt]) }}" class="px-4 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-600 whitespace-nowrap">Lihat Hasil</a>
            @elseif($uk->isOpenNow() || $attempt)
                <a href="{{ route('ujian.siswa.gate', $uk->ujian) }}" class="btn-primary px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap">{{ $attempt ? 'Lanjutkan' : 'Mulai' }}</a>
            @endif
        </div>
        @empty
        <div class="card p-10 text-center text-slate-400">
            <i data-lucide="file-check-2" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
            <p class="text-sm font-medium">Belum ada ujian untuk kelas Anda.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
