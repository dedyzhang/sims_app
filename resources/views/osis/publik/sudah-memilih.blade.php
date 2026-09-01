@extends('layouts.app')
@section('title', 'Terima Kasih — Pemilihan OSIS')

@section('content')
@php
    $namaPemilih = $pemilih->siswa->nama ?? $pemilih->guru->nama ?? $pemilih->nama_snapshot;
@endphp
<div class="max-w-md mx-auto pt-10 text-center space-y-4">
    <div class="w-16 h-16 rounded-2xl mx-auto flex items-center justify-center text-white shadow-lg bg-emerald-500">
        <i data-lucide="check-circle-2" class="w-8 h-8"></i>
    </div>
    <div>
        <h1 class="text-xl font-extrabold text-slate-800 dark:text-white">Terima Kasih, {{ $namaPemilih }}!</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Pilihan Anda untuk <strong>{{ $pemilih->pemilihan->nama ?? 'Pemilihan Ketua OSIS' }}</strong> sudah tercatat.
        </p>
    </div>

    <div class="card p-4 text-left">
        <p class="text-xs text-slate-400">
            Dipilih pada {{ $pemilih->sudah_memilih_at?->locale('id')->translatedFormat('d F Y, H:i') }} WIB.
            Suara hanya bisa dikirim sekali — kode QR ini tidak berlaku lagi.
        </p>
    </div>
</div>
@endsection
