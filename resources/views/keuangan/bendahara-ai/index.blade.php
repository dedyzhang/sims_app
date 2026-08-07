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

    @if(($ringkasanAntrian['menumpuk'] ?? false))
    <div class="card p-4 border-l-4 border-amber-400 bg-amber-50/50 dark:bg-amber-950/20">
        <p class="font-semibold text-amber-800 dark:text-amber-200 flex items-center gap-2">
            <i data-lucide="bell" class="w-4 h-4"></i> Antrian menumpuk
        </p>
        <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">
            {{ $ringkasanAntrian['menunggu'] }} bukti menunggu verifikasi
            @if($ringkasanAntrian['menunggu_lama'] > 0)
                ({{ $ringkasanAntrian['menunggu_lama'] }} sudah &gt; {{ config('keuangan-ai.digest.usia_hari_min', 3) }} hari)
            @endif
            · {{ $ringkasanAntrian['terverifikasi'] }} menunggu validasi bank.
            <a href="{{ route('keuangan.bendahara-ai.antrian', ['ta'=>$ta]) }}" class="font-semibold underline ml-1">Buka antrian</a>
        </p>
    </div>
    @endif

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
        <a href="{{ route('keuangan.bendahara-ai.rekonsiliasi', ['ta'=>$ta]) }}" class="card p-5 hover:shadow-md transition group">
            <div class="flex items-start gap-3">
                <span class="w-10 h-10 rounded-xl bg-sky-100 dark:bg-sky-900/40 text-sky-600 grid place-items-center"><i data-lucide="git-compare" class="w-5 h-5"></i></span>
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-100 group-hover:text-primary">Rekonsiliasi Bank</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Skor pencocokan mutasi rekening ↔ tagihan SPP.</p>
                </div>
            </div>
        </a>
        <a href="{{ route('keuangan.bendahara-ai.anomali', ['ta'=>$ta]) }}" class="card p-5 hover:shadow-md transition group">
            <div class="flex items-start gap-3">
                <span class="w-10 h-10 rounded-xl bg-rose-100 dark:bg-rose-900/40 text-rose-600 grid place-items-center"><i data-lucide="alert-triangle" class="w-5 h-5"></i></span>
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-100 group-hover:text-primary">Anomali
                        @if(($anomaliCount ?? 0) > 0)<span class="badge bg-rose-100 text-rose-700 ml-1">{{ $anomaliCount }}</span>@endif
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Duplikat bukti & nominal mencurigakan (flag saja).</p>
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
        <a href="{{ route('keuangan.bendahara-ai.wawasan', ['ta'=>$ta]) }}" class="card p-5 hover:shadow-md transition group">
            <div class="flex items-start gap-3">
                <span class="w-10 h-10 rounded-xl bg-violet-100 dark:bg-violet-900/40 text-violet-600 grid place-items-center"><i data-lucide="lightbulb" class="w-5 h-5"></i></span>
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-100 group-hover:text-primary">Wawasan & Ekspor</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Pola keterlambatan & hari bayar; unduh paket verifikasi PDF/Excel.</p>
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
