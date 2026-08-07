@extends('layouts.app')
@section('title', 'Penilaian Esai — ' . $ujian->judul)

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1">
            <a href="{{ route('ujian.index') }}" class="hover:underline">Ujian</a> /
            <a href="{{ route('ujian.show', $ujian) }}" class="hover:underline">{{ $ujian->judul }}</a> / Penilaian Esai
        </nav>
        <h1 class="page-title">Penilaian Esai</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $attempts->count() }} attempt menunggu penilaian.</p>
    </div>

    <div class="space-y-3">
        @forelse($attempts as $attempt)
        @php $siswa = $siswaByLogin->get($attempt->id_siswa); @endphp
        <div class="card p-5 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="font-semibold text-sm text-slate-800 dark:text-slate-100">{{ $siswa?->nama ?? 'Siswa tidak dikenal' }}</p>
                <p class="text-xs text-slate-400">{{ $siswa?->kelas?->tingkat }}{{ $siswa?->kelas?->kelas }} · Dikumpulkan {{ $attempt->selesai_pada?->format('d M Y H:i') }}</p>
            </div>
            <a href="{{ route('ujian.grading.show', [$ujian, $attempt]) }}" class="btn-primary px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap">Nilai</a>
        </div>
        @empty
        <div class="card p-10 text-center text-slate-400">
            <i data-lucide="check-circle-2" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
            <p class="text-sm font-medium">Tidak ada esai yang menunggu penilaian.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
