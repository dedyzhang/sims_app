@extends('layouts.app')
@section('title', 'Keuangan — SPP')

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title flex items-center gap-2">
                <span class="grid place-items-center w-9 h-9 rounded-xl text-white" style="background:linear-gradient(135deg,var(--cp),var(--cps))"><i data-lucide="wallet" class="w-5 h-5"></i></span>
                Keuangan SPP
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Tahun Ajaran {{ $ta }} · pilih kelas untuk kelola pembayaran</p>
        </div>
        <div class="flex gap-2 flex-wrap items-center">
            <form method="GET" class="flex items-center gap-2">
                <select name="ta" onchange="this.form.submit()" class="form-input !w-auto text-sm">
                    @foreach($taOptions as $opt)
                        <option value="{{ $opt }}" @selected($opt===$ta)>T.A. {{ $opt }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('keuangan.verifikasi', ['ta'=>$ta]) }}"
               class="relative flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-amber-200 dark:border-amber-700 text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition">
                <i data-lucide="badge-check" class="w-4 h-4"></i> Verifikasi
                @if($menungguTotal>0)
                <span class="absolute -top-1.5 -right-1.5 min-w-[20px] h-5 px-1 grid place-items-center text-[11px] font-bold text-white bg-rose-500 rounded-full">{{ $menungguTotal }}</span>
                @endif
            </a>
            <a href="{{ route('keuangan.bendahara-ai.index', ['ta'=>$ta]) }}"
               class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-indigo-200 dark:border-indigo-700 text-indigo-700 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition">
                <i data-lucide="sparkles" class="w-4 h-4"></i> <span class="hidden sm:inline">Asisten</span>
            </a>
            <a href="{{ route('keuangan.bank') }}"
               class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="landmark" class="w-4 h-4"></i> <span class="hidden sm:inline">Bank</span>
            </a>
        </div>
    </div>

    @if($menungguTotal>0)
    <a href="{{ route('keuangan.verifikasi', ['ta'=>$ta]) }}" class="block card p-4 border-l-4 border-amber-400 hover:shadow-md transition">
        <div class="flex items-center gap-3">
            <span class="grid place-items-center w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/40 text-amber-600"><i data-lucide="clock" class="w-5 h-5"></i></span>
            <div>
                <p class="font-bold text-slate-800 dark:text-slate-100">{{ $menungguTotal }} bukti pembayaran menunggu verifikasi</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Klik untuk meninjau & menyetujui pembayaran siswa.</p>
            </div>
        </div>
    </a>
    @endif

    {{-- Daftar kelas --}}
    @php $grouped = $kelasList->groupBy('tingkat'); @endphp
    @forelse($grouped as $tingkat => $list)
    <div>
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3 px-1">Tingkat {{ $tingkat }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($list as $k)
            @php
                $info  = $lunasPerKelas[$k->uuid] ?? null;
                $lunas = (int) ($info->lunas ?? 0);
                $total = $k->siswa_count * 12;
                $pct   = $total ? round($lunas / $total * 100) : 0;
            @endphp
            <a href="{{ route('keuangan.kelas', ['kelas'=>$k->uuid, 'ta'=>$ta]) }}" class="card p-5 flex flex-col gap-3 hover:shadow-md hover:-translate-y-0.5 transition">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-10 h-10 rounded-xl text-white font-bold text-lg flex items-center justify-center" style="background:var(--cp)">{{ $k->kelas }}</div>
                        <div>
                            <p class="font-bold text-slate-800 dark:text-slate-200">Kelas {{ $k->tingkat }}{{ $k->kelas }}</p>
                            <p class="text-xs text-slate-400">{{ $k->siswa_count }} siswa</p>
                        </div>
                    </div>
                    <i data-lucide="chevron-right" class="w-5 h-5 text-slate-300"></i>
                </div>
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-slate-500 dark:text-slate-400">Bulan lunas</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $lunas }} / {{ $total }} ({{ $pct }}%)</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                        <div class="h-full rounded-full" style="width: {{ $pct }}%; background:linear-gradient(90deg,var(--cp),var(--cps))"></div>
                    </div>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold mt-2">Rp {{ number_format((int)($info->nominal ?? 0), 0, ',', '.') }} terkumpul</p>
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @empty
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="school" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada kelas</p>
    </div>
    @endforelse
</div>
@endsection
