@extends('layouts.app')
@section('title', 'Rekap Bulanan Piket & Substitusi')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="page-title">Rekap Piket & Substitusi</h1>
            <p class="text-sm text-slate-500 mt-1">Frekuensi ketidakhadiran dan penugasan pengganti per guru untuk bahan evaluasi.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <a href="{{ route('piket.dashboard') }}" class="btn-white px-3 py-2 text-xs font-semibold rounded-lg shadow-sm">
                &larr; Dashboard
            </a>
            <a href="{{ route('piket.rekap.export', ['tahun' => $tahun, 'bulan' => $bulan]) }}" target="_blank" class="btn-primary flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition">
                <i data-lucide="download" class="w-4 h-4"></i> Unduh PDF
            </a>
        </div>
    </div>

    <div class="card p-5">
        <form method="GET" action="{{ route('piket.rekap') }}" class="flex items-end gap-3 mb-6">
            <div>
                <label class="form-label !mb-1">Bulan</label>
                <select name="bulan" class="form-input text-sm !py-2" onchange="this.form.submit()">
                    @foreach(range(1, 12) as $m)
                        <option value="{{ $m }}" {{ $bulan == $m ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::create()->month($m)->locale('id')->isoFormat('MMMM') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label !mb-1">Tahun</label>
                <select name="tahun" class="form-input text-sm !py-2" onchange="this.form.submit()">
                    @foreach(range(now()->year - 2, now()->year + 1) as $y)
                        <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        @if($rekap->isEmpty())
        <div class="p-12 text-center text-slate-400">
            <i data-lucide="folder-open" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
            <p class="font-medium">Tidak ada data ketidakhadiran atau penugasan pengganti pada periode ini.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                <thead class="text-xs uppercase bg-slate-50 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3 font-semibold rounded-tl-lg">Nama Guru</th>
                        <th class="px-4 py-3 font-semibold text-center border-l border-slate-200 dark:border-slate-600" colspan="5">Ketidakhadiran (Hari)</th>
                        <th class="px-4 py-3 font-semibold text-center border-l border-slate-200 dark:border-slate-600 rounded-tr-lg">Jadi Pengganti (Jam)</th>
                    </tr>
                    <tr class="text-[10px] bg-slate-100/50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                        <th class="px-4 py-2 border-r border-slate-200 dark:border-slate-700"></th>
                        <th class="px-2 py-2 text-center">Sakit</th>
                        <th class="px-2 py-2 text-center">Izin</th>
                        <th class="px-2 py-2 text-center">Dinas Luar</th>
                        <th class="px-2 py-2 text-center">Alpa</th>
                        <th class="px-2 py-2 text-center font-bold border-r border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200">Total</th>
                        <th class="px-4 py-2 text-center">Total Jam</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    @foreach($rekap as $row)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-200 border-r border-slate-100 dark:border-slate-700">{{ $row['nama'] }}</td>
                        <td class="px-2 py-3 text-center {{ $row['sakit'] > 0 ? 'text-amber-600 font-medium' : 'text-slate-300 dark:text-slate-600' }}">{{ $row['sakit'] ?: '-' }}</td>
                        <td class="px-2 py-3 text-center {{ $row['izin'] > 0 ? 'text-blue-600 font-medium' : 'text-slate-300 dark:text-slate-600' }}">{{ $row['izin'] ?: '-' }}</td>
                        <td class="px-2 py-3 text-center {{ $row['dinas_luar'] > 0 ? 'text-purple-600 font-medium' : 'text-slate-300 dark:text-slate-600' }}">{{ $row['dinas_luar'] ?: '-' }}</td>
                        <td class="px-2 py-3 text-center {{ $row['alpa'] > 0 ? 'text-rose-600 font-bold' : 'text-slate-300 dark:text-slate-600' }}">{{ $row['alpa'] ?: '-' }}</td>
                        <td class="px-2 py-3 text-center font-bold text-slate-700 dark:text-slate-300 border-r border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30">{{ $row['total_tidak_hadir'] }}</td>
                        <td class="px-4 py-3 text-center font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50/30 dark:bg-emerald-900/10">{{ $row['total_mengganti'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection
