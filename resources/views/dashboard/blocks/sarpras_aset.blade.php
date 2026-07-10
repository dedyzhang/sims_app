{{-- ===== Total Aset Sarpras (data dari controller, tanpa query di view) ===== --}}
@if($sarpras)
<a href="{{ route('sarpras.aset.index') }}" class="card p-5 flex items-center justify-between relative overflow-hidden group hover:border-primary/20 transition-all duration-300 h-full shadow-sm">
    <div class="min-w-0">
        <p class="text-xs text-slate-400 font-semibold mb-1 leading-tight break-words">Total Aset</p>
        <p class="text-2xl font-extrabold tracking-tight text-slate-750 dark:text-slate-200">{{ number_format($sarpras['totalAset'], 0, ',', '.') }} <span class="text-sm font-semibold text-slate-400">unit</span></p>
        <p class="text-[11px] text-slate-400 mt-0.5 leading-tight break-words truncate">{{ $sarpras['nilaiTotalRp'] }}</p>
    </div>
    <span class="grid place-items-center w-9 h-9 rounded-xl bg-primary/10 text-primary group-hover:scale-110 transition duration-300 flex-shrink-0">
        <i data-lucide="package" class="w-4 h-4"></i>
    </span>
</a>
@endif
