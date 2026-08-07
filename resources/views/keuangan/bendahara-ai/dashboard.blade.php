@extends('layouts.app')
@section('title', 'Dashboard Pendapatan SPP')

@section('content')
<div class="space-y-5" x-data="{
    bulan: @js(collect($ringkasan['bulan'])->map(fn($b) => ['label'=>$b['label'],'total'=>$b['total'],'jumlah'=>$b['jumlah']])->values()),
    maxTotal: @js(max(1, collect($ringkasan['bulan'])->max('total') ?? 1))
}">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <nav class="text-xs text-slate-400 mb-1">
                <a href="{{ route('keuangan.bendahara-ai.index', ['ta'=>$ta]) }}" class="hover:underline">Asisten Bendahara</a> / Dashboard
            </nav>
            <h1 class="page-title">Dashboard Pendapatan SPP</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Agregat transaksi <strong>lunas</strong> · T.A. {{ $ta }}</p>
        </div>
        <form method="GET" class="flex gap-2">
            <select name="ta" onchange="this.form.submit()" class="form-input !w-auto text-sm">
                @foreach($taOptions as $opt)
                    <option value="{{ $opt }}" @selected($opt===$ta)>T.A. {{ $opt }}</option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- Ringkasan tahun --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="card p-4">
            <p class="text-xs text-slate-400">Total Penerimaan</p>
            <p class="text-xl font-bold text-emerald-600">Rp {{ number_format($ringkasan['grand_total'], 0, ',', '.') }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-slate-400">Transaksi Lunas</p>
            <p class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ $ringkasan['grand_jumlah'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-slate-400">Bulan {{ \Carbon\Carbon::create($filterTahun, $filterBulan)->translatedFormat('F Y') }}</p>
            <p class="text-xl font-bold text-emerald-600">Rp {{ number_format($bulanIni['total'], 0, ',', '.') }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-slate-400">Menunggu / Terverifikasi</p>
            <p class="text-sm font-bold text-amber-600">{{ $bulanIni['menunggu'] }} / {{ $bulanIni['terverifikasi'] }}</p>
        </div>
    </div>

    {{-- Grafik batang sederhana (Alpine) --}}
    <div class="card p-5">
        <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-4">Penerimaan per Bulan (Lunas)</h2>
        <div class="flex items-end gap-1 h-40">
            <template x-for="(b, i) in bulan" :key="i">
                <div class="flex-1 flex flex-col items-center gap-1">
                    <div class="w-full bg-emerald-500/80 rounded-t-md transition-all min-h-[2px]"
                         :style="'height:' + (b.total / maxTotal * 100) + '%'"
                         :title="b.label + ': Rp ' + b.total.toLocaleString('id-ID')"></div>
                    <span class="text-[9px] text-slate-400 truncate w-full text-center" x-text="b.label.substring(0,3)"></span>
                </div>
            </template>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="card overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800 text-xs text-slate-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Bulan</th>
                    <th class="px-4 py-3 text-right">Jumlah Transaksi</th>
                    <th class="px-4 py-3 text-right">Total (Rp)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($ringkasan['bulan'] as $b)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-4 py-2.5 font-medium">{{ $b['label'] }}</td>
                    <td class="px-4 py-2.5 text-right">{{ $b['jumlah'] }}</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-emerald-600">Rp {{ number_format($b['total'], 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-slate-50 dark:bg-slate-800 font-bold">
                <tr>
                    <td class="px-4 py-3">Total</td>
                    <td class="px-4 py-3 text-right">{{ $ringkasan['grand_jumlah'] }}</td>
                    <td class="px-4 py-3 text-right text-emerald-600">Rp {{ number_format($ringkasan['grand_total'], 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
