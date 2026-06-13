@extends('layouts.app')
@section('title', 'Rekap Presensi Guru')

@push('styles')
<style>
    .rekap-grid { border-collapse:separate; border-spacing:0; }
    .rekap-grid th, .rekap-grid td { border-bottom:1px solid #f1ede7; white-space:nowrap; }
    .dark .rekap-grid th, .dark .rekap-grid td { border-color:#293548; }
    .rekap-grid .col-nama { position:sticky; left:0; z-index:2; background:#fff; box-shadow:1px 0 0 #eee; }
    .dark .rekap-grid .col-nama { background:#1e293b; box-shadow:1px 0 0 #334155; }
    .rekap-grid thead .col-nama { z-index:3; }
    .col-libur { background:#fbf7f2; }
    .dark .col-libur { background:#0f172a; }
</style>
@endpush

@section('content')
@php $breadcrumbs = [['label'=>'Presensi Guru','url'=>route('presensi-guru.index')], ['label'=>'Rekap','url'=>'#']]; @endphp
<div class="space-y-5" x-data="{ mode:'ringkasan' }">

    <div class="flex items-center gap-3">
        <a href="{{ route('presensi-guru.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="page-title">Rekap Presensi Guru</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Rentang tanggal &bull; batas terlambat <span class="font-semibold text-rose-500">{{ $batas }}</span></p>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('presensi-guru.rekap') }}" class="card p-4 flex flex-wrap gap-3 items-end">
        <div class="min-w-36">
            <label class="form-label">Dari tanggal</label>
            <input type="date" name="dari" value="{{ $dari }}" class="form-input" onchange="this.form.submit()">
        </div>
        <div class="min-w-36">
            <label class="form-label">Sampai tanggal</label>
            <input type="date" name="sampai" value="{{ $sampai }}" class="form-input" onchange="this.form.submit()">
        </div>
        <button type="submit" class="px-4 py-2.5 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition">Tampilkan</button>
    </form>

    @if($rekap->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="bar-chart-3" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada data guru.</p>
    </div>
    @else

    {{-- Toggle mode --}}
    <div class="flex items-center gap-1 p-1 rounded-xl bg-slate-100 dark:bg-slate-800 w-max">
        <button @click="mode='ringkasan'" :class="mode==='ringkasan' ? 'bg-white dark:bg-slate-700 text-primary shadow-sm' : 'text-slate-500'" class="px-4 py-2 rounded-lg text-sm font-semibold transition">Ringkasan</button>
        <button @click="mode='rincian'" :class="mode==='rincian' ? 'bg-white dark:bg-slate-700 text-primary shadow-sm' : 'text-slate-500'" class="px-4 py-2 rounded-lg text-sm font-semibold transition">Rincian Jam Masuk</button>
    </div>

    {{-- ===== Ringkasan (jumlah) ===== --}}
    <div x-show="mode==='ringkasan'" class="card overflow-hidden">
        <div class="table-responsive overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-10">#</th>
                        <th>Nama Guru</th>
                        <th class="text-center">Hadir</th>
                        <th class="text-center">Terlambat</th>
                        <th class="text-center">Izin</th>
                        <th class="text-center">Sakit</th>
                        <th class="text-center">Alpa</th>
                        <th class="text-center hide-mobile">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rekap as $i => $r)
                    @php $total = $r['hadir']+$r['izin']+$r['sakit']+$r['alpa']; @endphp
                    <tr>
                        <td class="text-slate-400 text-xs">{{ $i+1 }}</td>
                        <td>
                            <p class="font-medium text-slate-800 dark:text-slate-200">{{ $r['guru']->nama }}</p>
                            <p class="text-xs text-slate-400 font-mono">{{ $r['guru']->nip ?: $r['guru']->nik }}</p>
                        </td>
                        <td class="text-center"><span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300">{{ $r['hadir'] }}</span></td>
                        <td class="text-center">
                            @if($r['terlambat'] > 0)
                            <span class="badge bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300 font-bold">{{ $r['terlambat'] }}×</span>
                            @else
                            <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="text-center"><span class="badge bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300">{{ $r['izin'] }}</span></td>
                        <td class="text-center"><span class="badge bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300">{{ $r['sakit'] }}</span></td>
                        <td class="text-center"><span class="badge bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300">{{ $r['alpa'] }}</span></td>
                        <td class="text-center hide-mobile text-slate-500 font-semibold">{{ $total }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ===== Rincian jam masuk per tanggal ===== --}}
    <div x-show="mode==='rincian'" x-cloak class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="rekap-grid w-full text-xs">
                <thead>
                    <tr>
                        <th class="col-nama text-left px-3 py-2.5 text-[11px] font-bold uppercase tracking-wide text-slate-400">Nama Guru</th>
                        @foreach($dates as $d)
                        <th class="px-2 py-2 text-center font-semibold {{ $d['libur'] ? 'col-libur text-rose-400' : 'text-slate-500' }}">
                            <div class="text-[10px] opacity-70">{{ $d['hari'] }}</div>
                            <div>{{ $d['tgl'] }}</div>
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rekap as $r)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40">
                        <td class="col-nama px-3 py-2 font-medium text-slate-700 dark:text-slate-200 max-w-[180px] truncate">{{ $r['guru']->nama }}</td>
                        @foreach($dates as $d)
                        <td class="px-2 py-2 text-center {{ $d['libur'] ? 'col-libur' : '' }}">
                            @include('partials.rekap-cell', ['row' => $r['byDate'][$d['ymd']] ?? null, 'batas' => $batas])
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{-- Legenda --}}
        <div class="p-3 border-t border-slate-100 dark:border-slate-700 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-400">
            <span><span class="text-emerald-600 font-bold">07:15</span> = jam masuk</span>
            <span><span class="text-rose-600 font-bold">07:45</span> = terlambat</span>
            <span><span class="text-blue-600 font-bold">I</span> Izin</span>
            <span><span class="text-amber-600 font-bold">S</span> Sakit</span>
            <span><span class="text-rose-600 font-bold">A</span> Alpa</span>
            <span><span class="text-slate-300">·</span> tidak ada data</span>
        </div>
    </div>
    @endif
</div>
@endsection
