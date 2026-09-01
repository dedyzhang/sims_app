@extends('layouts.app')
@section('title', 'Pemilihan OSIS')

@section('content')
@php
    $namaPemilih = $pemilih->siswa->nama ?? $pemilih->guru->nama ?? $pemilih->nama_snapshot;
    $pesan = match ($status) {
        'ditutup' => 'Pemilihan sudah ditutup. Terima kasih atas partisipasi Anda.',
        'terjadwal' => 'Pemilihan belum dibuka. Pemilihan dijadwalkan mulai '
            . $jadwalMulai?->locale('id')->translatedFormat('d F Y, H:i') . ' WIB.',
        default => 'Pemilihan belum dibuka oleh panitia. Silakan coba lagi nanti.',
    };
@endphp
<div class="max-w-md mx-auto pt-10 text-center space-y-4">
    <div class="w-16 h-16 rounded-2xl mx-auto flex items-center justify-center text-white shadow-lg bg-slate-400">
        <i data-lucide="clock" class="w-8 h-8"></i>
    </div>
    <div>
        <h1 class="text-xl font-extrabold text-slate-800 dark:text-white">Halo, {{ $namaPemilih }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $pesan }}</p>
    </div>
</div>
@endsection
