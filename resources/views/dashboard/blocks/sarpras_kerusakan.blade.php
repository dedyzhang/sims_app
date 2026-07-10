{{-- ===== Kerusakan Terbuka Sarpras (data dari controller, tanpa query di view) ===== --}}
@if($sarpras)
<a href="{{ route('sarpras.kerusakan.index') }}" class="card p-5 flex items-center justify-between relative overflow-hidden group hover:border-primary/20 transition-all duration-300 h-full shadow-sm">
    <div class="min-w-0">
        <div class="flex items-center gap-2 mb-1">
            <p class="text-xs text-slate-400 font-semibold leading-tight break-words">Kerusakan Terbuka</p>
            @if($sarpras['kerusakanDarurat'] > 0)
                <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] rounded-full bg-rose-500 text-white text-[10px] font-black leading-none px-1">{{ $sarpras['kerusakanDarurat'] }}</span>
            @endif
        </div>
        <p class="text-2xl font-extrabold tracking-tight text-rose-500">{{ $sarpras['kerusakanTerbuka'] }} <span class="text-sm font-semibold text-slate-400">laporan</span></p>
        <p class="text-[11px] text-slate-400 mt-0.5 leading-tight break-words">Lihat detail &rarr;</p>
    </div>
    <span class="grid place-items-center w-9 h-9 rounded-xl bg-rose-100 dark:bg-rose-950/40 text-rose-500 group-hover:scale-110 transition duration-300 flex-shrink-0">
        <i data-lucide="wrench" class="w-4 h-4"></i>
    </span>
</a>
@endif
