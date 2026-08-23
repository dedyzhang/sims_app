@extends('layouts.app')
@section('title', 'Hasil Ujian')

@section('content')
<div class="max-w-lg mx-auto space-y-5">
    <div class="card p-8 text-center space-y-3">
        {{-- Nilai SENGAJA tidak ditampilkan di sini — begitu ujian dikumpulkan, jawaban siswa
             lain yg masih mengerjakan bisa saja masih terlihat di layar sekitarnya; nilai baru
             muncul lewat halaman Nilai Saya setelah diumumkan guru/sekolah. --}}
        <i data-lucide="check-circle-2" class="w-14 h-14 text-emerald-500 mx-auto"></i>
        <h1 class="text-lg font-bold text-slate-800 dark:text-slate-100">Ujian Selesai</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Terima kasih sudah mengerjakan. Nilai akan diumumkan oleh guru/sekolah.</p>
        <p class="text-xs text-slate-400 pt-2">{{ $ujian->judul }}</p>
    </div>

    @if($ujian->tampilkan_pembahasan && $attempt->status === 'dinilai')
    <div class="card p-5">
        <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-3 text-sm">Pembahasan</h2>
        <div class="space-y-4">
            @foreach($ujian->soal as $i => $soal)
            @php $jawaban = $attempt->jawaban->firstWhere('id_soal', $soal->uuid); @endphp
            <div class="border-b border-slate-100 dark:border-slate-700 pb-3 last:border-0">
                <div class="text-sm font-medium text-slate-700 dark:text-slate-200 flex gap-1.5">
                    <span>{{ $i + 1 }}.</span>
                    @include('ujian.partials.rich-body', ['html' => $soal->teks_soal])
                </div>
                <p class="text-xs mt-1 {{ $jawaban?->is_benar ? 'text-emerald-600' : 'text-rose-500' }}">
                    {{ $jawaban?->skor_diperoleh ?? 0 }} / {{ $soal->poinEfektif() }} poin
                </p>
                @if($soal->penjelasan)
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $soal->penjelasan }}</p>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <a href="{{ route('ujian.siswa.index') }}" class="block text-center text-sm text-primary hover:underline">← Kembali ke Daftar Ujian</a>
</div>
@endsection
