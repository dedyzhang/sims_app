{{-- Ringkasan pendapatan SPP kompak (dari dashboard Asisten Bendahara) --}}
@php
    $bulanChart = collect($ringkasan['bulan'])->map(fn ($b) => ['label' => $b['label'], 'total' => $b['total'], 'jumlah' => $b['jumlah']])->values();
    $maxTotal = max(1, $bulanChart->max('total') ?? 1);
@endphp
<div class="card p-4" x-data="{
    bulan: @js($bulanChart),
    maxTotal: @js($maxTotal),
}">
    <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
        <div>
            <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2">
                <i data-lucide="bar-chart-3" class="w-4 h-4 text-emerald-600"></i>
                Ringkasan Pendapatan SPP
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Agregat transaksi lunas · T.A. {{ $ta }}</p>
        </div>
        <form method="GET" class="flex gap-2">
            <select name="ta" onchange="this.form.submit()" class="form-input !w-auto text-xs">
                @foreach($taOptions as $opt)
                    <option value="{{ $opt }}" @selected($opt===$ta)>T.A. {{ $opt }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
        <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/30 px-3 py-2">
            <p class="text-[10px] text-slate-500 uppercase tracking-wide">Total Penerimaan</p>
            <p class="text-sm font-bold text-emerald-600">Rp {{ number_format($ringkasan['grand_total'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl bg-slate-50 dark:bg-slate-800/60 px-3 py-2">
            <p class="text-[10px] text-slate-500 uppercase tracking-wide">Transaksi Lunas</p>
            <p class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $ringkasan['grand_jumlah'] }}</p>
        </div>
        <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/30 px-3 py-2">
            <p class="text-[10px] text-slate-500 uppercase tracking-wide">Bulan Ini</p>
            <p class="text-sm font-bold text-emerald-600">Rp {{ number_format($bulanIni['total'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl bg-amber-50 dark:bg-amber-950/30 px-3 py-2">
            <p class="text-[10px] text-slate-500 uppercase tracking-wide">Menunggu / Terverifikasi</p>
            <p class="text-sm font-bold text-amber-600">{{ $bulanIni['menunggu'] }} / {{ $bulanIni['terverifikasi'] }}</p>
        </div>
    </div>

    <div class="flex items-end gap-1 h-24">
        <template x-for="(b, i) in bulan" :key="i">
            <div class="flex-1 flex flex-col items-center gap-1">
                <div class="w-full bg-emerald-500/80 rounded-t-md transition-all min-h-[2px]"
                     :style="'height:' + (b.total / maxTotal * 100) + '%'"
                     :title="b.label + ': Rp ' + b.total.toLocaleString('id-ID')"></div>
                <span class="text-[10px] sm:text-xs text-slate-400 truncate w-full text-center" x-text="b.label.substring(0,3)"></span>
            </div>
        </template>
    </div>
</div>
