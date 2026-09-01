@extends('layouts.app')
@section('title', 'Latihan Sudah Berakhir')

@php($isKiosk = true)

@section('content')
<div class="max-w-md mx-auto pt-10 text-center space-y-4">
    <div class="w-16 h-16 rounded-2xl mx-auto flex items-center justify-center text-white shadow-lg bg-slate-400">
        <i data-lucide="flag" class="w-8 h-8"></i>
    </div>
    <div>
        <h1 class="text-xl font-extrabold text-slate-800 dark:text-white">Sesi Latihan Sudah Berakhir</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Kode ini sudah tidak aktif. Minta guru membuat sesi latihan baru dan bagikan QR/kode yang baru.
        </p>
    </div>
</div>
@endsection
