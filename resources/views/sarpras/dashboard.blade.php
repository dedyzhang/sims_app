@extends('layouts.app')
@section('title', 'Dashboard Sarpras')

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-extrabold text-slate-800 dark:text-slate-100">Sarana &amp; Prasarana</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manajemen aset, gedung interaktif, pengadaan barang, peminjaman, perbaikan, dan mutasi barang.</p>
    </div>

    {{-- Tab navigasi --}}
    @php
        $tabs = [
            ['Dashboard Sarpras', 'layout-dashboard', 'sarpras.dashboard',     ['sarpras.dashboard']],
            ['Denah Interaktif',  'map',              'sarpras.denah.index',   ['sarpras.denah.*','sarpras.ruangan.*']],
            ['Ruangan & Booking', 'building-2',       'sarpras.peminjaman.index', ['sarpras.booking.*']],
            ['Maintenance Lapor', 'triangle-alert',   'sarpras.kerusakan.index',  ['sarpras.kerusakan.*']],
            ['Inventaris Barang', 'package',          'sarpras.aset.index',       ['sarpras.aset.*','sarpras.kategori.*']],
            ['Pengadaan Aset',    'shopping-cart',    'sarpras.pengadaan.index',  ['sarpras.pengadaan.*','sarpras.supplier.*']],
            ['Peminjaman Aset',   'hand-helping',     'sarpras.peminjaman.index', ['sarpras.peminjaman.*']],
            ['Perbaikan & Teknis','wrench',           'sarpras.perbaikan.index',  ['sarpras.perbaikan.*','sarpras.teknisi.*']],
        ];
    @endphp
    <div class="border-b border-slate-200 dark:border-slate-700 -mt-1">
        <nav class="flex gap-1 overflow-x-auto no-scrollbar">
            @foreach($tabs as [$label, $icon, $route, $patterns])
                @php $active = request()->routeIs(...$patterns); @endphp
                <a href="{{ route($route) }}"
                   class="flex items-center gap-2 px-4 py-3 text-sm font-semibold whitespace-nowrap border-b-2 transition
                          {{ $active
                                ? 'border-amber-500 text-amber-600 dark:text-amber-400'
                                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200' }}">
                    <i data-lucide="{{ $icon }}" class="w-[18px] h-[18px]"></i>
                    {{ $label }}
                </a>
            @endforeach
        </nav>
    </div>

    {{-- Kartu statistik --}}
    @php
        $stats = [
            ['Total Aset',        number_format($totalAset, 0, ',', '.') . ' unit', 'archive',        'amber',   'text-slate-800 dark:text-slate-100'],
            ['Nilai Aset',        $nilaiTotalRp,                                     'banknote',       'emerald', 'text-slate-800 dark:text-slate-100'],
            ['Kerusakan Terbuka', $kerusakanTerbuka . ' laporan',                    'triangle-alert', 'rose',    'text-rose-600 dark:text-rose-400'],
            ['Peminjaman Aktif',  $peminjamanAktif . ' item',                        'hand-helping',   'blue',    'text-blue-600 dark:text-blue-400'],
        ];
        $chip = [
            'amber'   => 'bg-amber-100 dark:bg-amber-900/40 text-amber-500',
            'emerald' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-500',
            'rose'    => 'bg-rose-100 dark:bg-rose-900/40 text-rose-500',
            'blue'    => 'bg-blue-100 dark:bg-blue-900/40 text-blue-500',
        ];
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($stats as [$label, $val, $icon, $warna, $valClass])
        <div class="card p-5 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ $label }}</p>
                <p class="text-2xl font-extrabold mt-1 {{ $valClass }} truncate">{{ $val }}</p>
            </div>
            <span class="grid place-items-center w-11 h-11 rounded-xl flex-shrink-0 {{ $chip[$warna] }}"><i data-lucide="{{ $icon }}" class="w-5 h-5"></i></span>
        </div>
        @endforeach
    </div>

    {{-- Panel: kategori, kondisi, booking --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Aset per Kategori --}}
        <div class="card p-5">
            <div class="flex items-center gap-2 mb-4">
                <span class="grid place-items-center w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/40 text-amber-500"><i data-lucide="layers" class="w-4 h-4"></i></span>
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Aset per Kategori</h2>
            </div>
            <ul class="space-y-1">
                @forelse($asetPerKategori as $row)
                <li>
                    <a href="{{ route('sarpras.aset.index', ['kategori_id' => $row->kategori_id]) }}"
                       class="flex items-center justify-between py-2.5 px-1 border-b border-slate-100 dark:border-slate-700/60 last:border-0 hover:text-amber-600 dark:hover:text-amber-400 group">
                        <span class="text-sm text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400">{{ $row->kategori?->nama ?? 'Tanpa Kategori' }}</span>
                        <span class="text-xs font-bold px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ $row->jml }} unit</span>
                    </a>
                </li>
                @empty
                <li class="text-sm text-slate-400 py-6 text-center">Belum ada aset.</li>
                @endforelse
            </ul>
        </div>

        {{-- Kondisi Fisik Barang --}}
        <div class="card p-5">
            <div class="flex items-center gap-2 mb-4">
                <span class="grid place-items-center w-8 h-8 rounded-lg bg-rose-100 dark:bg-rose-900/40 text-rose-500"><i data-lucide="activity" class="w-4 h-4"></i></span>
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Kondisi Fisik Barang</h2>
            </div>
            @php
                $kondisiMeta = [
                    'baik'         => ['Baik',         '#10b981'],
                    'rusak_ringan' => ['Rusak Ringan', '#f59e0b'],
                    'rusak_berat'  => ['Rusak Berat',  '#ef4444'],
                    'hilang'       => ['Hilang',       '#94a3b8'],
                ];
                $maxKondisi = max(1, (int) $asetPerKondisi->max());
            @endphp
            <div class="space-y-3.5">
                @forelse($kondisiMeta as $key => [$label, $color])
                    @php $jml = (int) ($asetPerKondisi[$key] ?? 0); @endphp
                    @if($jml > 0)
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="flex items-center gap-2 text-sm font-semibold" style="color:{{ $color }}">
                                <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $color }}"></span>{{ $label }}
                            </span>
                            <span class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ $jml }} unit</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                            <div class="h-full rounded-full" style="width: {{ round($jml / $maxKondisi * 100) }}%; background:{{ $color }}"></div>
                        </div>
                    </div>
                    @endif
                @empty
                @endforelse
                @if($asetPerKondisi->sum() === 0)
                    <p class="text-sm text-slate-400 py-6 text-center">Belum ada data kondisi.</p>
                @endif
            </div>
        </div>

        {{-- Booking Ruangan Hari Ini --}}
        <div class="card p-5">
            <div class="flex items-center gap-2 mb-4">
                <span class="grid place-items-center w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/40 text-blue-500"><i data-lucide="calendar-check" class="w-4 h-4"></i></span>
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Booking Ruangan Hari Ini</h2>
            </div>
            <div class="space-y-2.5">
                @forelse($bookingHariIni as $b)
                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-700/60">
                    <p class="font-bold text-slate-800 dark:text-slate-100">{{ $b->ruangan?->nama ?? $b->ruangan?->kode ?? 'Ruangan' }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Keperluan: {{ $b->keperluan }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Pukul: {{ $b->mulai->format('H:i') }} - {{ $b->selesai->format('H:i') }} WIB</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Oleh: {{ $b->pemohon?->name ?? '-' }}</p>
                </div>
                @empty
                <div class="text-center py-8 text-slate-400">
                    <i data-lucide="calendar-x" class="w-9 h-9 mx-auto mb-2 opacity-30"></i>
                    <p class="text-sm">Belum ada booking ruangan hari ini.</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .no-scrollbar::-webkit-scrollbar { height: 4px; }
    .no-scrollbar::-webkit-scrollbar-thumb { background: rgb(203 213 225 / .6); border-radius: 9999px; }
    .dark .no-scrollbar::-webkit-scrollbar-thumb { background: rgb(71 85 105 / .6); }
</style>
@endpush
