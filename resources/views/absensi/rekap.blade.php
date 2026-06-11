@extends('layouts.app')
@section('title', 'Rekap Absensi')

@section('content')
@php $breadcrumbs = [['label'=>'Absensi','url'=>route('absensi.index')], ['label'=>'Rekap','url'=>'#']]; @endphp
<div class="space-y-5">

    <div class="flex items-center gap-3">
        <a href="{{ route('absensi.index', ['kelas'=>$selectedKelas]) }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="page-title">Rekap Absensi</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Ringkasan kehadiran per bulan</p>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('absensi.rekap') }}" class="card p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-40">
            <label class="form-label">Kelas</label>
            <select name="kelas" class="form-select" onchange="this.form.submit()">
                @foreach($kelasList as $k)
                <option value="{{ $k->uuid }}" @selected($selectedKelas===$k->uuid)>Kelas {{ $k->tingkat }}{{ $k->kelas }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-40">
            <label class="form-label">Bulan</label>
            <input type="month" name="bulan" value="{{ $bulan }}" class="form-input" onchange="this.form.submit()">
        </div>
        <button type="submit" class="px-4 py-2.5 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition">Tampilkan</button>
    </form>

    @if($rekap->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="bar-chart-3" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada data untuk ditampilkan.</p>
    </div>
    @else
    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-10">#</th>
                        <th>Nama Siswa</th>
                        <th class="text-center">Hadir</th>
                        <th class="text-center">Izin</th>
                        <th class="text-center">Sakit</th>
                        <th class="text-center">Alpa</th>
                        <th class="text-center hide-mobile">Total</th>
                        <th class="text-center">% Hadir</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rekap as $i => $r)
                    @php $total = $r['hadir']+$r['izin']+$r['sakit']+$r['alpa']; $pct = $total ? round($r['hadir']/$total*100) : 0; @endphp
                    <tr>
                        <td class="text-slate-400 text-xs">{{ $i+1 }}</td>
                        <td>
                            <p class="font-medium text-slate-800 dark:text-slate-200">{{ $r['siswa']->nama }}</p>
                            <p class="text-xs text-slate-400 font-mono">{{ $r['siswa']->nis }}</p>
                        </td>
                        <td class="text-center"><span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300">{{ $r['hadir'] }}</span></td>
                        <td class="text-center"><span class="badge bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300">{{ $r['izin'] }}</span></td>
                        <td class="text-center"><span class="badge bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300">{{ $r['sakit'] }}</span></td>
                        <td class="text-center"><span class="badge bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300">{{ $r['alpa'] }}</span></td>
                        <td class="text-center hide-mobile text-slate-500 font-semibold">{{ $total }}</td>
                        <td class="text-center">
                            <div class="flex items-center gap-2 justify-center">
                                <div class="w-14 h-1.5 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden hide-mobile">
                                    <div class="h-full rounded-full" style="width:{{ $pct }}%;background:{{ $pct>=75 ? '#10b981' : ($pct>=50 ? '#f59e0b' : '#ef4444') }}"></div>
                                </div>
                                <span class="text-sm font-bold {{ $pct>=75 ? 'text-emerald-600' : ($pct>=50 ? 'text-amber-600' : 'text-rose-600') }}">{{ $pct }}%</span>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
