@extends('layouts.app')
@section('title', 'Asisten Bendahara SPP')

@section('content')
<div class="space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1"><a href="{{ route('keuangan.index', ['ta'=>$ta]) }}" class="hover:underline">Keuangan</a> / Asisten Bendahara</nav>
        <h1 class="page-title flex items-center gap-2">
            <span class="grid place-items-center w-9 h-9 rounded-xl text-white" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)"><i data-lucide="sparkles" class="w-5 h-5"></i></span>
            Asisten Bendahara SPP
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Tahun Ajaran {{ $ta }} · alat operasional harian (bukan narasi pimpinan)</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="{{ route('keuangan.bendahara-ai.antrian', ['ta'=>$ta]) }}" class="card p-5 hover:shadow-md transition group">
            <div class="flex items-start gap-3">
                <span class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 grid place-items-center"><i data-lucide="list-ordered" class="w-5 h-5"></i></span>
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-100 group-hover:text-primary">Antrian Prioritas</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Verifikasi diurutkan skor mendesak — aturan tetap, bukan AI.</p>
                </div>
            </div>
        </a>
        <a href="{{ route('keuangan.bendahara-ai.dashboard', ['ta'=>$ta]) }}" class="card p-5 hover:shadow-md transition group">
            <div class="flex items-start gap-3">
                <span class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 grid place-items-center"><i data-lucide="bar-chart-3" class="w-5 h-5"></i></span>
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-100 group-hover:text-primary">Dashboard SPP</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Pendapatan bulanan dari transaksi terverifikasi/lunas.</p>
                </div>
            </div>
        </a>
        <a href="{{ route('keuangan.bendahara-ai.log', ['ta'=>$ta]) }}" class="card p-5 hover:shadow-md transition group">
            <div class="flex items-start gap-3">
                <span class="w-10 h-10 rounded-xl bg-sky-100 dark:bg-sky-900/40 text-sky-600 grid place-items-center"><i data-lucide="history" class="w-5 h-5"></i></span>
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-100 group-hover:text-primary">Jejak Audit</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Riwayat perubahan status verifikasi & impor rekening koran.</p>
                </div>
            </div>
        </a>
    </div>

    <div class="card p-4 border-l-4 border-indigo-400 text-sm text-slate-600 dark:text-slate-300">
        <p class="font-semibold text-slate-800 dark:text-slate-100 mb-1">Human-in-the-loop</p>
        <p class="text-xs">OCR hanya menyarankan isian — bendahara wajib konfirmasi sebelum menyimpan. Nominal resmi dihitung sistem (BIGINT), bukan AI.</p>
    </div>
</div>
@endsection
