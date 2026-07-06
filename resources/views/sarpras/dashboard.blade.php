@extends('sarpras.layouts.app')
@section('title', 'Dashboard Sarpras')

@section('sarpras_body')
<div class="space-y-6">

    {{-- Kartu statistik terintegrasi --}}
    @php
        $stats = [
            ['Total Aset',        number_format($totalAset, 0, ',', '.') . ' unit', 'archive',        'amber',   'text-slate-800 dark:text-slate-100', route('sarpras.aset.index')],
            ['Nilai Aset',        $nilaiTotalRp,                                     'banknote',       'emerald', 'text-slate-800 dark:text-slate-100', route('sarpras.aset.index')],
            ['Kerusakan Terbuka', $kerusakanTerbuka . ' laporan',                    'triangle-alert', 'rose',    'text-rose-600 dark:text-rose-400', route('sarpras.kerusakan.index')],
            ['Peminjaman Aktif',  $peminjamanAktif . ' item',                        'hand-helping',   'blue',    'text-blue-600 dark:text-blue-400', route('sarpras.peminjaman.index')],
        ];
        $chip = [
            'amber'   => 'bg-amber-100 dark:bg-amber-900/40 text-amber-500',
            'emerald' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-500',
            'rose'    => 'bg-rose-100 dark:bg-rose-900/40 text-rose-500',
            'blue'    => 'bg-blue-100 dark:bg-blue-900/40 text-blue-500',
        ];
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" data-drag-container="dashboard_stats">
        @foreach($stats as [$label, $val, $icon, $warna, $valClass, $url])
        <a href="{{ $url }}" data-drag-id="{{ Str::slug($label) }}" class="card card-hover p-5 flex items-start justify-between gap-3 group transition-all duration-200">
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 group-hover:text-slate-500 dark:group-hover:text-slate-400 transition-colors">{{ $label }}</p>
                <p class="text-2xl font-extrabold mt-1 {{ $valClass }} truncate">{{ $val }}</p>
            </div>
            <span class="grid place-items-center w-11 h-11 rounded-xl flex-shrink-0 {{ $chip[$warna] }} group-hover:scale-110 transition-transform duration-200">
                <i data-lucide="{{ $icon }}" class="w-5 h-5"></i>
            </span>
        </a>
        @endforeach
    </div>

    {{-- Panel Utama (Kategori & Kondisi) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4" data-drag-container="dashboard_charts">

        {{-- Aset per Kategori --}}
        <div class="card p-5 flex flex-col justify-between transition-all duration-200" data-drag-id="kategori_card">
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <span class="grid place-items-center w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/40 text-amber-500"><i data-lucide="layers" class="w-4 h-4"></i></span>
                    <h2 class="font-bold text-slate-800 dark:text-slate-100">Aset per Kategori</h2>
                </div>
                <ul class="space-y-1">
                    @foreach($asetPerKategori as $row)
                    <li>
                        <a href="{{ route('sarpras.aset.index', ['kategori_id' => $row->kategori_id]) }}"
                           class="flex items-center justify-between py-2.5 px-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/40 hover:text-amber-600 dark:hover:text-amber-400 transition-all duration-150 group">
                            <span class="text-sm text-slate-700 dark:text-slate-200 group-hover:text-amber-600 dark:group-hover:text-amber-400 font-medium">{{ $row->kategori?->nama ?? 'Tanpa Kategori' }}</span>
                            <span class="text-xs font-bold px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 group-hover:bg-amber-100 dark:group-hover:bg-amber-950/40 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">{{ $row->jml }} unit</span>
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Kondisi Fisik Barang (Donut Chart) --}}
        <div class="card p-5 transition-all duration-200" data-drag-id="kondisi_card">
            <div class="flex items-center gap-2 mb-4">
                <span class="grid place-items-center w-8 h-8 rounded-lg bg-rose-100 dark:bg-rose-900/40 text-rose-500"><i data-lucide="activity" class="w-4 h-4"></i></span>
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Kondisi Fisik Barang</h2>
            </div>
            @php
                $condColors = ['baik' => '#10b981', 'rusak_ringan' => '#f59e0b', 'rusak_berat' => '#ef4444', 'hilang' => '#94a3b8'];
                $condLabels = ['baik' => 'Baik', 'rusak_ringan' => 'Rusak Ringan', 'rusak_berat' => 'Rusak Berat', 'hilang' => 'Hilang'];
                $totalKondisi = (int) $asetPerKondisi->sum();

                $stops = [];
                $acc = 0.0;
                foreach (['baik', 'rusak_ringan', 'rusak_berat', 'hilang'] as $k) {
                    $val = (int) ($asetPerKondisi[$k] ?? 0);
                    if ($val > 0 && $totalKondisi > 0) {
                        $pct = $val / $totalKondisi * 100;
                        $stops[] = $condColors[$k] . ' ' . round($acc, 2) . '% ' . round($acc + $pct, 2) . '%';
                        $acc += $pct;
                    }
                }
                $donut = count($stops) ? 'conic-gradient(' . implode(',', $stops) . ')' : '#e2e8f0';
            @endphp
            
            @if($totalKondisi === 0)
                <p class="text-sm text-slate-400 py-10 text-center">Belum ada data aset.</p>
            @else
            <div class="flex flex-col sm:flex-row items-center gap-6 py-2">
                <div class="relative flex-shrink-0" style="width:140px;height:140px">
                    <div class="w-full h-full rounded-full" style="background:{{ $donut }}"></div>
                    <div class="absolute inset-0 m-auto rounded-full bg-white dark:bg-slate-800 grid place-items-center" style="width:84px;height:84px">
                        <div class="text-center">
                            <p class="text-xl font-extrabold text-slate-800 dark:text-slate-100">{{ number_format($totalKondisi) }}</p>
                            <p class="text-[10px] text-slate-400">unit</p>
                        </div>
                    </div>
                </div>
                <div class="w-full space-y-1.5">
                    @foreach(['baik', 'rusak_ringan', 'rusak_berat', 'hilang'] as $key)
                        @php $jml = (int) ($asetPerKondisi[$key] ?? 0); @endphp
                        @if($jml > 0)
                        <a href="{{ route('sarpras.aset.index', ['kondisi' => $key]) }}" 
                           class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-all duration-150 group">
                            <span class="flex items-center gap-2 text-sm font-bold" style="color:{{ $condColors[$key] }}">
                                <span class="w-2.5 h-2.5 rounded-full group-hover:scale-125 transition-transform" style="background:{{ $condColors[$key] }}"></span>
                                {{ $condLabels[$key] }}
                            </span>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 group-hover:bg-amber-100 dark:group-hover:bg-amber-950/40 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">
                                {{ $jml }} unit
                            </span>
                        </a>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Panel Aktivitas & Booking --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4" data-drag-container="dashboard_bookings">

        {{-- Laporan Kerusakan Terbaru --}}
        <div class="card p-5 transition-all duration-200" data-drag-id="kerusakan_card">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="grid place-items-center w-8 h-8 rounded-lg bg-rose-100 dark:bg-rose-900/40 text-rose-500"><i data-lucide="alert-circle" class="w-4 h-4"></i></span>
                    <h2 class="font-bold text-slate-800 dark:text-slate-100">Kerusakan Terbaru</h2>
                </div>
                <a href="{{ route('sarpras.kerusakan.index') }}" class="text-xs font-semibold text-rose-500 hover:underline">Lihat Semua</a>
            </div>
            <div class="space-y-2.5">
                @foreach($kerusakanTerbaru as $k)
                <a href="{{ route('sarpras.kerusakan.show', $k) }}" class="block p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-700/60 hover:border-rose-400 dark:hover:border-rose-500/50 hover:bg-slate-100/50 dark:hover:bg-slate-800/60 transition-all duration-150 group">
                    <div class="flex justify-between items-start gap-2">
                        <p class="font-bold text-sm text-slate-800 dark:text-slate-100 group-hover:text-rose-500 transition-colors truncate flex-1">{{ $k->deskripsi }}</p>
                        @php
                            $badgeColor = [
                                'dilaporkan' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400',
                                'diterima'   => 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400',
                                'ditolak'    => 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400',
                                'selesai'    => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400',
                            ];
                        @endphp
                        <span class="badge {{ $badgeColor[$k->status] ?? 'bg-slate-100 text-slate-700' }} shrink-0">{{ ucfirst($k->status) }}</span>
                    </div>
                    <div class="flex items-center justify-between mt-2 text-[11px] text-slate-400 dark:text-slate-500">
                        <span>Aset: <b class="text-slate-600 dark:text-slate-300 font-semibold">{{ $k->aset?->nama ?? 'Aset' }}</b></span>
                        <span>{{ $k->created_at?->diffForHumans() }}</span>
                    </div>
                </a>
                @endforeach
            </div>
        </div>

        {{-- Booking Ruangan Hari Ini --}}
        <div class="card p-5 transition-all duration-200" data-drag-id="booking_card">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="grid place-items-center w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/40 text-blue-500"><i data-lucide="calendar-check" class="w-4 h-4"></i></span>
                    <h2 class="font-bold text-slate-800 dark:text-slate-100">Booking Ruangan Hari Ini</h2>
                </div>
                <a href="{{ route('sarpras.booking.index') }}" class="text-xs font-semibold text-blue-500 hover:underline">Lihat Semua</a>
            </div>
            <div class="space-y-2.5">
                @foreach($bookingHariIni as $b)
                <a href="{{ route('sarpras.booking.index') }}" class="block p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-700/60 hover:border-blue-400 dark:hover:border-blue-500/50 hover:bg-slate-100/50 dark:hover:bg-slate-800/60 transition-all duration-150 group">
                    <p class="font-bold text-slate-800 dark:text-slate-100 group-hover:text-blue-500 transition-colors">{{ $b->ruangan?->nama ?? $b->ruangan?->kode ?? 'Ruangan' }}</p>
                    <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 mt-1">
                        <span>Keperluan: <b class="text-slate-600 dark:text-slate-300 font-semibold">{{ $b->keperluan }}</b></span>
                        <span>{{ $b->mulai->format('H:i') }} - {{ $b->selesai->format('H:i') }} WIB</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Oleh: {{ $b->pemohon?->name ?? '-' }}</p>
                </a>
                @endforeach
            </div>
        </div>

    </div>

</div>
@endsection
