@extends('layouts.app')
@section('title', 'Verifikasi Pembayaran SPP')

@section('content')
@php
    // Tab default: utamakan yang ada isinya (cek bukti dulu, lalu validasi).
    $defaultTab = $menungguCount > 0 ? 'cek' : ($terverifikasiCount > 0 ? 'validasi' : 'cek');
@endphp
<div x-data="{ tab: '{{ $defaultTab }}' }" class="space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <nav class="text-xs text-slate-400 mb-1"><a href="{{ route('keuangan.index', ['ta'=>$ta]) }}" class="hover:underline">Keuangan</a> / Verifikasi</nav>
            <h1 class="page-title">Verifikasi Pembayaran</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Tahun Ajaran {{ $ta }}</p>
        </div>
        <form method="GET" class="flex items-center gap-2 flex-wrap">
            <div class="relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama / kelas / NIS…" class="form-input text-sm !pl-9 w-56">
                @if($q)
                <a href="{{ route('keuangan.verifikasi', ['ta'=>$ta]) }}" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-rose-500"><i data-lucide="x" class="w-4 h-4"></i></a>
                @endif
            </div>
            <select name="ta" onchange="this.form.submit()" class="form-input !w-auto text-sm">
                @foreach($taOptions as $opt)
                    <option value="{{ $opt }}" @selected($opt===$ta)>T.A. {{ $opt }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-sm font-semibold">Cari</button>
        </form>
    </div>

    @if($q)
    <p class="text-xs text-slate-500 dark:text-slate-400 -mt-1">Hasil pencarian untuk "<span class="font-semibold text-slate-700 dark:text-slate-200">{{ $q }}</span>" · <a href="{{ route('keuangan.verifikasi', ['ta'=>$ta]) }}" class="text-primary hover:underline">reset</a></p>
    @endif

    {{-- Tabs --}}
    <div class="flex gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-2xl">
        <button @click="tab='cek'" type="button"
                class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl text-sm font-semibold transition"
                :class="tab==='cek' ? 'bg-white dark:bg-slate-700 text-amber-600 dark:text-amber-400 shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700'">
            <span class="w-5 h-5 rounded-full bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300 grid place-items-center text-[11px] font-extrabold">1</span>
            <span class="hidden sm:inline">Verifikasi Bukti</span><span class="sm:hidden">Cek</span>
            @if($menungguCount)<span class="badge bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300">{{ $menungguCount }}</span>@endif
        </button>
        <button @click="tab='validasi'" type="button"
                class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl text-sm font-semibold transition"
                :class="tab==='validasi' ? 'bg-white dark:bg-slate-700 text-sky-600 dark:text-sky-400 shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700'">
            <span class="w-5 h-5 rounded-full bg-sky-100 dark:bg-sky-900 text-sky-700 dark:text-sky-300 grid place-items-center text-[11px] font-extrabold">2</span>
            <span class="hidden sm:inline">Validasi Rekening Koran</span><span class="sm:hidden">Validasi</span>
            @if($terverifikasiCount)<span class="badge bg-sky-100 dark:bg-sky-900 text-sky-700 dark:text-sky-300">{{ $terverifikasiCount }}</span>@endif
        </button>
    </div>

    {{-- TAB 1: Menunggu cek bukti --}}
    <div x-show="tab==='cek'" x-transition.opacity class="space-y-3">
        <p class="text-xs text-slate-500 dark:text-slate-400 px-1">Periksa bukti transfer yang dikirim orang tua. Setujui untuk menandai <span class="font-semibold text-sky-600 dark:text-sky-400">terverifikasi</span> (lanjut ke validasi rekening koran).</p>
        @forelse($menungguGroups as $group)
            @include('keuangan.partials.verif-card', ['group' => $group, 'mode' => 'verify'])
        @empty
            <div class="card p-10 text-center text-slate-400">
                <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
                <p class="text-sm font-medium">Tidak ada bukti baru yang menunggu dicek.</p>
            </div>
        @endforelse
    </div>

    {{-- TAB 2: Menunggu validasi rekening koran --}}
    <div x-show="tab==='validasi'" x-transition.opacity x-cloak class="space-y-3">
        <p class="text-xs text-slate-500 dark:text-slate-400 px-1">Cocokkan dana masuk dengan <span class="font-semibold">rekening koran resmi bank</span>, lalu tandai <span class="font-semibold text-emerald-600 dark:text-emerald-400">lunas</span>.</p>

        {{-- Import otomatis dari laporan transaksi VA (.txt) bank --}}
        <div class="card p-4 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-start gap-3">
                <span class="w-9 h-9 rounded-xl bg-sky-100 dark:bg-sky-900 text-sky-600 dark:text-sky-300 grid place-items-center flex-shrink-0"><i data-lucide="file-up" class="w-4 h-4"></i></span>
                <div>
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Upload Laporan Transaksi VA (.txt)</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Cocok otomatis via 6 digit belakang VA siswa &amp; nominal — tampil pratinjau dulu, bisa diubah manual sebelum diterapkan.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('keuangan.import-rekening-koran.preview') }}" enctype="multipart/form-data" class="flex items-center gap-2">
                @csrf
                <input type="file" name="file" accept=".txt" required
                       class="text-xs file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:text-xs file:font-semibold hover:file:bg-primary/20">
                <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-sm font-semibold whitespace-nowrap">Lihat Pratinjau</button>
            </form>
        </div>

        @forelse($terverifikasiGroups as $group)
            @include('keuangan.partials.verif-card', ['group' => $group, 'mode' => 'validate'])
        @empty
            <div class="card p-10 text-center text-slate-400">
                <i data-lucide="landmark" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
                <p class="text-sm font-medium">Tidak ada pembayaran yang menunggu validasi rekening koran.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
